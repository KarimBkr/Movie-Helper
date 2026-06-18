<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectMember;

class ProjectAccessService
{
    public function findMemberAccess(string $projectId, string $userId): ?array
    {
        $member = ProjectMember::where('project_id', $projectId)
            ->where('user_id', $userId)
            ->first();

        if (! $member) {
            return null;
        }

        $project = Project::where('id', $projectId)
            ->where('status', 'active')
            ->first();

        if (! $project) {
            return null;
        }

        return ['project' => $project, 'member' => $member];
    }
}
