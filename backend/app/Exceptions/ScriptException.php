<?php

namespace App\Exceptions;

/**
 * Erreurs liées aux scripts (upload, parsing). Codes conformes à CLAUDE.md.
 */
class ScriptException extends ApiException
{
    public static function notFound(): self
    {
        return new self('Script introuvable.', 'SCRIPT_NOT_FOUND', 404);
    }

    public static function invalidFdx(string $reason): self
    {
        return new self($reason, 'INVALID_FDX_FILE', 422);
    }

    public static function alreadyParsed(): self
    {
        return new self('Ce script a déjà été dépouillé.', 'SCRIPT_ALREADY_PARSED', 409);
    }
}
