<?php

namespace App\DTOs;

/**
 * Résultat du parsing déterministe d'un fichier FDX.
 *
 * @property-read ParsedSequence[] $sequences
 */
readonly class FdxParseResult
{
    /**
     * @param  ParsedSequence[]  $sequences  séquences dans l'ordre du document
     */
    public function __construct(
        public array $sequences,
    ) {}

    public function sequenceCount(): int
    {
        return count($this->sequences);
    }

    /**
     * Vrai si au moins une séquence demande une relecture humaine.
     */
    public function hasNeedsReview(): bool
    {
        foreach ($this->sequences as $sequence) {
            if ($sequence->parseStatus === 'needs_review') {
                return true;
            }
        }

        return false;
    }
}
