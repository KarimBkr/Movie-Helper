<?php

namespace App\Exceptions;

/**
 * Erreurs liées à l'orchestration de l'analyse IA (L-06). Codes conformes à CLAUDE.md.
 */
class AnalysisException extends ApiException
{
    public static function notFound(): self
    {
        return new self('Analyse introuvable.', 'ANALYSIS_NOT_FOUND', 404);
    }

    public static function alreadyRunning(): self
    {
        return new self('Une analyse est déjà en cours pour ce script.', 'ANALYSIS_ALREADY_RUNNING', 409);
    }

    public static function scriptNotParsed(): self
    {
        return new self('Le script doit être dépouillé avant de lancer l\'analyse IA.', 'INVALID_FDX_FILE', 422);
    }

    public static function noSequences(): self
    {
        return new self('Aucune séquence à analyser pour ce script.', 'INVALID_FDX_FILE', 422);
    }
}
