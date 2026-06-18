<?php

namespace App\Actions;

use App\Exceptions\SequenceException;
use App\Models\Sequence;
use App\Models\SequenceElement;
use App\Services\Claude\SequenceBreakdownTool;

/**
 * Création / mise à jour manuelle d'un élément de dépouillement (L-08/L-09).
 *
 * C'est ici qu'on applique les règles métier de correction humaine :
 *   - un élément créé à la main est en statut 'manuel' ;
 *   - éditer un élément 'ia' le fait passer en 'corrige' (trace de la décision
 *     humaine — « l'IA propose, l'assistant décide ») ;
 *   - règle 7 : tout élément 'ia' sans source_text est rejeté (ici on bloque
 *     surtout le retour arrière vers 'ia' sans source_text).
 */
class UpsertSequenceElementAction
{
    /**
     * @param  array<string,mixed>  $data
     */
    public function create(Sequence $sequence, array $data, string $userId): SequenceElement
    {
        $category = $this->requireValidCategory($data['category'] ?? null);
        $value = $this->requireValue($data['value'] ?? null);
        $status = $this->normalizeStatus($data['status'] ?? 'manuel');
        $sourceText = $this->normalizeSourceText($data['source_text'] ?? null);

        $this->guardSourceText($status, $sourceText);

        return SequenceElement::create([
            'project_id' => $sequence->project_id,
            'sequence_id' => $sequence->id,
            'category' => $category,
            'value' => $value,
            'source_text' => $sourceText,
            'confidence' => $this->normalizeConfidence($data['confidence'] ?? null),
            'status' => $status,
            'note' => $this->normalizeNote($data['note'] ?? null),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function update(SequenceElement $element, array $data, string $userId): SequenceElement
    {
        if (array_key_exists('category', $data)) {
            $element->category = $this->requireValidCategory($data['category']);
        }

        if (array_key_exists('value', $data)) {
            $element->value = $this->requireValue($data['value']);
        }

        if (array_key_exists('source_text', $data)) {
            $element->source_text = $this->normalizeSourceText($data['source_text']);
        }

        if (array_key_exists('confidence', $data)) {
            $element->confidence = $this->normalizeConfidence($data['confidence']);
        }

        if (array_key_exists('note', $data)) {
            $element->note = $this->normalizeNote($data['note']);
        }

        // Éditer un élément proposé par l'IA = décision humaine → 'corrige'.
        // Un statut explicite dans la requête prime (ex : 'verifie', 'manuel').
        if (array_key_exists('status', $data)) {
            $element->status = $this->normalizeStatus($data['status']);
        } elseif ($element->status === 'ia') {
            $element->status = 'corrige';
        }

        $this->guardSourceText($element->status, $element->source_text);

        $element->updated_by = $userId;
        $element->save();

        return $element;
    }

    private function requireValidCategory(mixed $category): string
    {
        if (! is_string($category) || ! in_array($category, SequenceBreakdownTool::ELEMENT_CATEGORIES, true)) {
            throw SequenceException::invalidElement(
                'Catégorie d\'élément invalide.',
                ['category' => ['Catégorie non reconnue.']],
            );
        }

        return $category;
    }

    private function requireValue(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        if ($value === '') {
            throw SequenceException::invalidElement(
                'La valeur de l\'élément est requise.',
                ['value' => ['La valeur est requise.']],
            );
        }

        return $value;
    }

    private function normalizeStatus(mixed $status): string
    {
        $allowed = ['ia', 'verifie', 'corrige', 'manuel'];

        if (! is_string($status) || ! in_array($status, $allowed, true)) {
            throw SequenceException::invalidElement(
                'Statut d\'élément invalide.',
                ['status' => ['Statut non reconnu.']],
            );
        }

        return $status;
    }

    private function normalizeConfidence(mixed $confidence): string
    {
        $allowed = SequenceBreakdownTool::CONFIDENCE_LEVELS;

        if (is_string($confidence) && in_array($confidence, $allowed, true)) {
            return $confidence;
        }

        return 'medium';
    }

    private function normalizeSourceText(mixed $sourceText): ?string
    {
        if (! is_string($sourceText)) {
            return null;
        }

        $sourceText = trim($sourceText);

        return $sourceText === '' ? null : $sourceText;
    }

    private function normalizeNote(mixed $note): ?string
    {
        if (! is_string($note)) {
            return null;
        }

        $note = trim($note);

        return $note === '' ? null : $note;
    }

    /**
     * Règle 7 : un élément 'ia' sans source_text est rejeté (alignée sur la
     * contrainte DB sequence_elements_ai_source_required).
     */
    private function guardSourceText(string $status, ?string $sourceText): void
    {
        if ($status === 'ia' && ($sourceText === null || trim($sourceText) === '')) {
            throw SequenceException::missingSourceText();
        }
    }
}
