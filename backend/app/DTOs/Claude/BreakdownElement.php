<?php

namespace App\DTOs\Claude;

/**
 * Un élément à dépouiller proposé par Claude (futur sequence_element).
 *
 * source_text est garanti non vide à la construction : règle 7 de CLAUDE.md
 * — « Tout élément IA sans source_text est rejeté par Laravel ». Les éléments
 * sans source_text sont écartés en amont par SequenceBreakdown::fromToolInput().
 */
readonly class BreakdownElement
{
    public function __construct(
        public string $category,
        public string $value,
        public string $sourceText,
        public string $confidence,
        public string $note,
    ) {}

    /**
     * @param  array<string,mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            category: (string) ($data['category'] ?? ''),
            value: (string) ($data['value'] ?? ''),
            sourceText: (string) ($data['source_text'] ?? ''),
            confidence: (string) ($data['confidence'] ?? 'low'),
            note: (string) ($data['note'] ?? ''),
        );
    }

    /**
     * Vrai si l'élément est exploitable : value et source_text non vides.
     */
    public function hasSourceText(): bool
    {
        return trim($this->sourceText) !== '' && trim($this->value) !== '';
    }
}
