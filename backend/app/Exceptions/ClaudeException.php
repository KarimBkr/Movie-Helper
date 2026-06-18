<?php

namespace App\Exceptions;

/**
 * Erreurs liées à l'appel Claude pour l'analyse de séquence (L-04/L-05).
 *
 * CLAUDE_TOOL_USE_FAILED couvre tout ce qui empêche d'obtenir un bloc
 * tool_use exploitable : HTTP en échec, absence de bloc tool_use, mauvais
 * nom d'outil, ou structure d'input inattendue.
 */
class ClaudeException extends ApiException
{
    public static function toolUseFailed(string $reason): self
    {
        return new self(
            "L'analyse IA a échoué : {$reason}",
            'CLAUDE_TOOL_USE_FAILED',
            500,
        );
    }

    public static function notConfigured(): self
    {
        return new self(
            'La clé API Anthropic est absente de la configuration.',
            'CLAUDE_TOOL_USE_FAILED',
            500,
        );
    }
}
