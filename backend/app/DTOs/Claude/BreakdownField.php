<?php

namespace App\DTOs\Claude;

/**
 * Un champ « sourcé » renvoyé par Claude : {value, source_text, confidence}.
 *
 * Utilisé pour les champs directs de la séquence (resume, decor, int_ext,
 * jour_nuit, huitiemes). La value reste mixte car huitiemes est numérique.
 */
readonly class BreakdownField
{
    public function __construct(
        public mixed $value,
        public string $sourceText,
        public string $confidence,
    ) {}

    /**
     * @param  array<string,mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            value: $data['value'] ?? null,
            sourceText: (string) ($data['source_text'] ?? ''),
            confidence: (string) ($data['confidence'] ?? 'low'),
        );
    }
}
