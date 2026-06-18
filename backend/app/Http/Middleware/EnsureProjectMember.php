<?php

namespace App\Http\Middleware;

use App\Services\ProjectAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProjectMember
{
    public function __construct(private readonly ProjectAccessService $projectAccess) {}

    public function handle(Request $request, Closure $next): Response
    {
        $projectId = $request->route('id');
        $userId    = $request->attributes->get('auth_user')->id;

        $access = $this->projectAccess->findMemberAccess($projectId, $userId);

        if (! $access) {
            return response()->json([
                'message' => 'Projet introuvable.',
                'code'    => 'PROJECT_NOT_FOUND',
                'errors'  => [],
            ], 404);
        }

        $request->attributes->set('project', $access['project']);
        $request->attributes->set('project_member', $access['member']);

        return $next($request);
    }
}
