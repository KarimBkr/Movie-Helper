<?php

namespace App\Http\Controllers;

use App\Actions\CreateProjectAction;
use App\Http\Requests\StoreProjectRequest;
use App\Models\Project;
use App\Models\ProjectMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __construct(private readonly CreateProjectAction $createProject) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->attributes->get('auth_user')->id;

        $projects = Project::whereHas('members', fn ($q) => $q->where('user_id', $userId))
            ->with(['members' => fn ($q) => $q->where('user_id', $userId)])
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Project $p) => $this->formatProject($p, $p->members->first()));

        return response()->json(['data' => $projects]);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $project = $this->createProject->execute(
            title: $request->validated('title'),
            type: $request->validated('type'),
            ownerId: $user->id,
        );

        $member = ProjectMember::where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->first();

        return response()->json(['data' => $this->formatProject($project, $member)], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $userId = $request->attributes->get('auth_user')->id;

        $member = ProjectMember::where('project_id', $id)
            ->where('user_id', $userId)
            ->first();

        if (! $member) {
            return response()->json([
                'message' => 'Projet introuvable.',
                'code'    => 'PROJECT_NOT_FOUND',
                'errors'  => [],
            ], 404);
        }

        $project = Project::where('id', $id)->where('status', 'active')->first();

        if (! $project) {
            return response()->json([
                'message' => 'Projet introuvable.',
                'code'    => 'PROJECT_NOT_FOUND',
                'errors'  => [],
            ], 404);
        }

        return response()->json(['data' => $this->formatProject($project, $member)]);
    }

    private function formatProject(Project $project, ?ProjectMember $member): array
    {
        return [
            'id'         => $project->id,
            'title'      => $project->title,
            'slug'       => $project->slug,
            'type'       => $project->type,
            'role'       => $member?->role ?? 'viewer',
            'created_at' => $project->created_at,
        ];
    }
}
