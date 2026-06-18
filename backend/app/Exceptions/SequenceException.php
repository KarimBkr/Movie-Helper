<?php

namespace App\Exceptions;

/**
 * Erreurs liées au tableau de dépouillement : séquences et éléments (L-08/L-09).
 *
 * SEQUENCE_NOT_FOUND / ELEMENT_NOT_FOUND ne figurent pas encore dans la liste
 * canonique C-05 (docs/api-errors.md) → ajoutés et à valider avec Jihad.
 *
 * @param  array<string,mixed>  $errors
 */
class SequenceException extends ApiException
{
    public static function sequenceNotFound(): self
    {
        return new self('Séquence introuvable.', 'SEQUENCE_NOT_FOUND', 404);
    }

    public static function elementNotFound(): self
    {
        return new self('Élément introuvable.', 'ELEMENT_NOT_FOUND', 404);
    }

    public static function missingSourceText(): self
    {
        return new self(
            'Un élément en statut « ia » doit avoir un source_text non vide.',
            'MISSING_SOURCE_TEXT',
            422,
        );
    }

    /**
     * @param  array<string,mixed>  $errors
     */
    public static function invalidElement(string $reason, array $errors = []): self
    {
        return new self($reason, 'INVALID_SEQUENCE_ELEMENT', 422, $errors);
    }
}
