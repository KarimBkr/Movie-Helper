<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProjectOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $member = $request->attributes->get('project_member');

        if ($member->role !== 'owner') {
            return response()->json([
                'message' => 'Action réservée au propriétaire du projet.',
                'code'    => 'FORBIDDEN_PROJECT',
                'errors'  => [],
            ], 403);
        }

        return $next($request);
    }
}
