<?php

namespace Tests\Feature;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthMiddlewareTest extends TestCase
{
    private const SECRET = 'test-jwt-secret-longue-au-moins-32-chars';

    protected function setUp(): void
    {
        parent::setUp();
        config(['supabase.jwt_secret' => self::SECRET]);

        Route::middleware('auth.supabase')->get('/_test_auth', function (): string {
            return 'ok';
        });
    }

    private function validToken(): string
    {
        return JWT::encode([
            'sub'   => 'uuid-feature-test',
            'email' => 'feature@test.com',
            'role'  => 'authenticated',
            'exp'   => time() + 3600,
            'iat'   => time(),
            'aud'   => 'authenticated',
        ], self::SECRET, 'HS256');
    }

    public function test_request_without_token_returns_401(): void
    {
        $response = $this->getJson('/_test_auth');

        $response->assertStatus(401)
                 ->assertJsonFragment(['code' => 'UNAUTHORIZED']);
    }

    public function test_request_with_invalid_token_returns_401(): void
    {
        $response = $this->getJson('/_test_auth', [
            'Authorization' => 'Bearer token.invalide.ici',
        ]);

        $response->assertStatus(401)
                 ->assertJsonFragment(['code' => 'UNAUTHORIZED']);
    }

    public function test_request_with_valid_token_passes_through(): void
    {
        $response = $this->getJson('/_test_auth', [
            'Authorization' => 'Bearer '.$this->validToken(),
        ]);

        $response->assertStatus(200);
    }

    public function test_valid_token_sets_auth_user_on_request(): void
    {
        Route::middleware('auth.supabase')->get('/_test_auth_user', function (\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse {
            $user = $request->attributes->get('auth_user');

            return response()->json(['id' => $user->id, 'email' => $user->email]);
        });

        $response = $this->getJson('/_test_auth_user', [
            'Authorization' => 'Bearer '.$this->validToken(),
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['id' => 'uuid-feature-test', 'email' => 'feature@test.com']);
    }
}
