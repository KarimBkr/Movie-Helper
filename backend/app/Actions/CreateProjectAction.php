<?php

namespace App\Actions;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Services\ProjectSlugService;
use Illuminate\Support\Facades\DB;

class CreateProjectAction
{
    public function __construct(private readonly ProjectSlugService $slugService) {}

    public function execute(string $title, string $type, string $ownerId): Project
    {
        $slug = $this->slugService->generate($title);

        return DB::transaction(function () use ($title, $type, $slug, $ownerId): Project {
            $project = Project::create([
                'title'    => $title,
                'slug'     => $slug,
                'type'     => $type,
                'owner_id' => $ownerId,
            ]);

            ProjectMember::firstOrCreate(
                ['project_id' => $project->id, 'user_id' => $ownerId],
                ['role' => 'owner']
            );

            return $project;
        });
    }
}
