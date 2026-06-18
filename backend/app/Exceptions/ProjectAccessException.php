<?php

namespace App\Exceptions;

/**
 * Erreurs d'accès projet (membership / rôle). Codes conformes à CLAUDE.md.
 */
class ProjectAccessException extends ApiException
{
    public static function notFound(): self
    {
        return new self('Projet introuvable.', 'PROJECT_NOT_FOUND', 404);
    }

    public static function forbidden(): self
    {
        return new self('Action non autorisée sur ce projet.', 'FORBIDDEN_PROJECT', 403);
    }
}
