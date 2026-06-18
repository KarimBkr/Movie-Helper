<?php

namespace App\Services;

use App\Exceptions\ProjectAccessException;
use App\Models\Project;
use App\Models\ProjectMember;

/**
 * Vérification centralisée de l'accès à un projet (règle : « Toujours vérifier
 * membership avant toute opération »). Laravel utilise la service_role key
 * uniquement après être passé par ce service (règle 4 de CLAUDE.md).
 *
 * Rôles : owner = tous droits ; viewer = lecture + export uniquement.
 */
class ProjectAccessService
{
    /**
     * Le projet doit exister, être actif, et l'utilisateur en être membre.
     * Renvoie le Project. Sinon → ProjectAccessException (404 PROJECT_NOT_FOUND).
     */
    public function requireMember(string $projectId, string $userId): Project
    {
        $member = $this->memberOrNull($projectId, $userId);

        if ($member === null) {
            throw ProjectAccessException::notFound();
        }

        $project = Project::where('id', $projectId)->where('status', 'active')->first();

        if ($project === null) {
            throw ProjectAccessException::notFound();
        }

        return $project;
    }

    /**
     * Comme requireMember, mais exige le rôle owner (écritures).
     * Membre sans le rôle owner → ProjectAccessException (403 FORBIDDEN_PROJECT).
     */
    public function requireOwner(string $projectId, string $userId): Project
    {
        $member = $this->memberOrNull($projectId, $userId);

        if ($member === null) {
            throw ProjectAccessException::notFound();
        }

        if ($member->role !== 'owner') {
            throw ProjectAccessException::forbidden();
        }

        $project = Project::where('id', $projectId)->where('status', 'active')->first();

        if ($project === null) {
            throw ProjectAccessException::notFound();
        }

        return $project;
    }

    public function role(string $projectId, string $userId): ?string
    {
        return $this->memberOrNull($projectId, $userId)?->role;
    }

    private function memberOrNull(string $projectId, string $userId): ?ProjectMember
    {
        return ProjectMember::where('project_id', $projectId)
            ->where('user_id', $userId)
            ->first();
    }
}
