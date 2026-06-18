<?php

namespace App\DTOs\Claude;

use App\Exceptions\ClaudeException;

/**
 * Pré-dépouillement d'une séquence renvoyé par Claude (input du tool_use
 * `extract_sequence_breakdown`), validé et typé.
 *
 * `fromToolInput()` est la frontière de confiance entre le `input` brut de
 * l'API et la persistance Laravel : structure vérifiée, éléments sans
 * source_text écartés (règle 7 de CLAUDE.md).
 */
readonly class SequenceBreakdown
{
    /**
     * @param  array<string,BreakdownField>  $sequenceFields  champs directs (resume, decor…)
     * @param  BreakdownElement[]  $elements  éléments valides (source_text non vide)
     * @param  BreakdownElement[]  $rejectedElements  éléments écartés faute de source_text
     * @param  string[]  $flags
     * @param  string[]  $notes
     */
    public function __construct(
        public ?string $sceneNumber,
        public array $sequenceFields,
        public array $elements,
        public array $rejectedElements,
        public array $flags,
        public array $notes,
        public ?string $toolUseId = null,
        public array $rawInput = [],
    ) {}

    /**
     * Construit le DTO depuis l'input brut du bloc tool_use.
     *
     * @param  array<string,mixed>  $input
     *
     * @throws ClaudeException si la structure attendue est absente.
     */
    public static function fromToolInput(array $input, ?string $toolUseId = null): self
    {
        if (! isset($input['sequence']) || ! is_array($input['sequence'])) {
            throw ClaudeException::toolUseFailed('Réponse Claude sans bloc « sequence » exploitable.');
        }

        $sequence = $input['sequence'];

        $fields = [];
        foreach (['resume', 'decor', 'int_ext', 'jour_nuit', 'huitiemes'] as $key) {
            if (isset($sequence[$key]) && is_array($sequence[$key])) {
                $fields[$key] = BreakdownField::fromArray($sequence[$key]);
            }
        }

        $valid = [];
        $rejected = [];
        foreach (self::asList($input['elements'] ?? []) as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $element = BreakdownElement::fromArray($raw);

            // Règle 7 : pas de source_text → rejeté (jamais persisté).
            if ($element->hasSourceText()) {
                $valid[] = $element;
            } else {
                $rejected[] = $element;
            }
        }

        return new self(
            sceneNumber: isset($sequence['scene_number']) ? (string) $sequence['scene_number'] : null,
            sequenceFields: $fields,
            elements: $valid,
            rejectedElements: $rejected,
            flags: self::asStringList($input['flags'] ?? []),
            notes: self::asStringList($input['notes'] ?? []),
            toolUseId: $toolUseId,
            rawInput: $input,
        );
    }

    public function field(string $key): ?BreakdownField
    {
        return $this->sequenceFields[$key] ?? null;
    }

    /**
     * @return array<int,mixed>
     */
    private static function asList(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @return string[]
     */
    private static function asStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : '', $value),
            fn (string $v) => $v !== '',
        ));
    }
}
