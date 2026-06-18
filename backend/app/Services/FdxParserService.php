<?php

namespace App\Services;

use App\DTOs\FdxParseResult;
use App\DTOs\ParsedSequence;
use RuntimeException;
use SimpleXMLElement;

/**
 * Parser FDX déterministe (L-02).
 *
 * Transforme le XML Final Draft en séquences. Il extrait ce qui est présent
 * dans le document, il n'invente rien et n'appelle jamais l'IA (règle 6 de CLAUDE.md).
 * `resume` et `huitiemes` sont laissés à l'IA et ne sont pas produits ici.
 *
 * Référence structure : docs/fdx-structure-notes.md.
 */
class FdxParserService
{
    /**
     * Types de paragraphe qui ouvrent une nouvelle séquence.
     */
    private const SCENE_HEADING = 'Scene Heading';

    /**
     * Parse le contenu XML d'un .fdx et retourne les séquences ordonnées.
     *
     * @throws RuntimeException si le XML est invalide ou n'est pas un FinalDraft.
     */
    public function parse(string $xmlContent): FdxParseResult
    {
        $xml = $this->loadXml($xmlContent);

        if ($xml->getName() !== 'FinalDraft') {
            throw new RuntimeException('Le fichier ne ressemble pas à un Final Draft (.fdx) : racine FinalDraft absente.');
        }

        if (! isset($xml->Content)) {
            throw new RuntimeException('Le fichier FDX ne contient aucun bloc Content.');
        }

        $sequences = [];
        $current = null;
        $displayOrder = 0;

        foreach ($xml->Content->Paragraph as $paragraph) {
            $type = trim((string) $paragraph['Type']);
            $text = $this->paragraphText($paragraph);

            if ($type === self::SCENE_HEADING) {
                if ($current !== null) {
                    $sequences[] = $this->finalizeSequence($current);
                }

                $displayOrder++;
                $current = $this->startSequence($displayOrder, $paragraph, $text);

                continue;
            }

            // Texte avant tout Scene Heading → séquence implicite à relire.
            if ($current === null) {
                if ($text === '') {
                    continue;
                }

                $displayOrder++;
                $current = $this->startImplicitSequence($displayOrder);
            }

            if ($text !== '') {
                $current['rawText'][] = $text;
            }
        }

        if ($current !== null) {
            $sequences[] = $this->finalizeSequence($current);
        }

        return new FdxParseResult($sequences);
    }

    /**
     * Charge le XML en bloquant les entités externes (protection XXE :
     * un .fdx provient d'un utilisateur).
     */
    private function loadXml(string $xmlContent): SimpleXMLElement
    {
        if (trim($xmlContent) === '') {
            throw new RuntimeException('Le fichier FDX est vide.');
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($xmlContent, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOENT);

            if ($xml === false) {
                throw new RuntimeException('Le fichier FDX n\'est pas un XML valide.');
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Démarre une séquence à partir d'un Scene Heading.
     *
     * @return array{displayOrder:int,sceneNumber:?string,sceneHeading:string,intExt:string,decor:?string,subDecor:?string,dayNight:string,rawText:string[],parseStatus:string}
     */
    private function startSequence(int $displayOrder, SimpleXMLElement $paragraph, string $headingText): array
    {
        $heading = $this->parseHeading($headingText);
        $sceneNumber = trim((string) $paragraph['Number']);

        return [
            'displayOrder' => $displayOrder,
            'sceneNumber' => $sceneNumber !== '' ? $sceneNumber : null,
            'sceneHeading' => $headingText,
            'intExt' => $heading['intExt'],
            'decor' => $heading['decor'],
            'subDecor' => $heading['subDecor'],
            'dayNight' => $heading['dayNight'],
            'rawText' => [$headingText],
            'parseStatus' => $heading['needsReview'] ? 'needs_review' : 'parsed',
        ];
    }

    /**
     * Démarre une séquence implicite (texte avant le premier Scene Heading).
     *
     * @return array{displayOrder:int,sceneNumber:?string,sceneHeading:?string,intExt:string,decor:?string,subDecor:?string,dayNight:string,rawText:string[],parseStatus:string}
     */
    private function startImplicitSequence(int $displayOrder): array
    {
        return [
            'displayOrder' => $displayOrder,
            'sceneNumber' => null,
            'sceneHeading' => null,
            'intExt' => 'UNKNOWN',
            'decor' => null,
            'subDecor' => null,
            'dayNight' => 'UNKNOWN',
            'rawText' => [],
            'parseStatus' => 'needs_review',
        ];
    }

    /**
     * @param  array<string,mixed>  $current
     */
    private function finalizeSequence(array $current): ParsedSequence
    {
        return new ParsedSequence(
            displayOrder: $current['displayOrder'],
            sceneNumber: $current['sceneNumber'],
            sceneHeading: $current['sceneHeading'],
            intExt: $current['intExt'],
            decor: $current['decor'],
            subDecor: $current['subDecor'],
            dayNight: $current['dayNight'],
            rawText: implode("\n", $current['rawText']),
            parseStatus: $current['parseStatus'],
        );
    }

    /**
     * Concatène tous les runs <Text> d'un <Paragraph> dans l'ordre,
     * sans ajouter d'espace artificiel (les espaces sont dans les runs).
     */
    private function paragraphText(SimpleXMLElement $paragraph): string
    {
        $buffer = '';

        foreach ($paragraph->Text as $run) {
            $buffer .= (string) $run;
        }

        return trim($buffer);
    }

    /**
     * Parse un Scene Heading français « INT./EXT. DÉCOR - MOMENT » de façon
     * déterministe. En cas d'ambiguïté → 'UNKNOWN' + needsReview.
     *
     * @return array{intExt:string,decor:?string,subDecor:?string,dayNight:string,needsReview:bool}
     */
    private function parseHeading(string $heading): array
    {
        $heading = trim($heading);
        $needsReview = false;

        // 1. int_ext : préfixe en tête de ligne.
        $intExt = $this->extractIntExt($heading, $remainder);
        if ($intExt === 'UNKNOWN') {
            $needsReview = true;
        }

        // 2. day_night : suffixe après le dernier séparateur ' - '.
        $dayNight = 'UNKNOWN';
        $decorPart = $remainder;

        if (preg_match('/\s[-–]\s(?!.*\s[-–]\s)(.+)$/u', $remainder, $m)) {
            $moment = $this->normalizeDayNight($m[1]);
            if ($moment !== 'UNKNOWN') {
                $dayNight = $moment;
                // Le décor est tout ce qui précède le dernier séparateur ' - MOMENT'.
                $decorPart = trim(preg_replace('#\s[-–]\s'.preg_quote($m[1], '#').'$#u', '', $remainder));
            }
        }

        if ($dayNight === 'UNKNOWN') {
            $needsReview = true;
        }

        // 3. decor / sub_decor : la partie centrale, éventuellement scindée sur ',' ou ' - '.
        [$decor, $subDecor] = $this->splitDecor($decorPart);

        if ($decor === null) {
            $needsReview = true;
        }

        return [
            'intExt' => $intExt,
            'decor' => $decor,
            'subDecor' => $subDecor,
            'dayNight' => $dayNight,
            'needsReview' => $needsReview,
        ];
    }

    /**
     * Extrait le préfixe INT/EXT/INT-EXT et renseigne le reste de la ligne.
     */
    private function extractIntExt(string $heading, ?string &$remainder): string
    {
        $remainder = $heading;

        // INT./EXT., INT/EXT, INT-EXT (mixte) en premier (plus spécifique).
        if (preg_match('/^(INT\.?\s*[\/\-]\s*EXT|EXT\.?\s*[\/\-]\s*INT)\.?\s*/iu', $heading, $m)) {
            $remainder = trim(substr($heading, strlen($m[0])));

            return 'INT/EXT';
        }

        if (preg_match('/^INT\.?\s+/iu', $heading, $m)) {
            $remainder = trim(substr($heading, strlen($m[0])));

            return 'INT';
        }

        if (preg_match('/^EXT\.?\s+/iu', $heading, $m)) {
            $remainder = trim(substr($heading, strlen($m[0])));

            return 'EXT';
        }

        return 'UNKNOWN';
    }

    /**
     * Normalise un libellé de moment vers l'enum métier, ou 'UNKNOWN'.
     */
    private function normalizeDayNight(string $raw): string
    {
        $raw = mb_strtoupper(trim($raw), 'UTF-8');

        // On retire la ponctuation finale éventuelle.
        $raw = rtrim($raw, " .\t");

        return match (true) {
            str_contains($raw, 'JOUR') => 'JOUR',
            str_contains($raw, 'NUIT') => 'NUIT',
            str_contains($raw, 'MATIN') => 'MATIN',
            str_contains($raw, 'SOIR') => 'SOIR',
            str_contains($raw, 'AUBE') => 'AUBE',
            str_contains($raw, 'CREPUSCULE') || str_contains($raw, 'CRÉPUSCULE') => 'CREPUSCULE',
            default => 'UNKNOWN',
        };
    }

    /**
     * Scinde la partie décor en decor principal + sous-lieu (après ',' ou ' - ').
     *
     * @return array{0:?string,1:?string} [decor, subDecor]
     */
    private function splitDecor(string $decorPart): array
    {
        $decorPart = trim($decorPart);

        if ($decorPart === '') {
            return [null, null];
        }

        // Un sous-lieu peut suivre une virgule ou un tiret.
        if (preg_match('/^(.+?)\s*[,\-–]\s*(.+)$/u', $decorPart, $m)) {
            $decor = trim($m[1]);
            $subDecor = trim($m[2]);

            return [
                $decor !== '' ? $decor : null,
                $subDecor !== '' ? $subDecor : null,
            ];
        }

        return [$decorPart, null];
    }
}
