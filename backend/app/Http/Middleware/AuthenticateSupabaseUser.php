<?php

namespace App\Http\Middleware;

use App\Services\SupabaseAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuthenticateSupabaseUser
{
    public function __construct(private readonly SupabaseAuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'message' => 'Token d\'authentification manquant.',
                'code'    => 'UNAUTHORIZED',
                'errors'  => [],
            ], 401);
        }

        try {
            $user = $this->auth->decodeToken($token);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Token invalide ou expiré.',
                'code'    => 'UNAUTHORIZED',
                'errors'  => [],
            ], 401);
        }

        $request->attributes->set('auth_user', $user);

        return $next($request);
    }
}
