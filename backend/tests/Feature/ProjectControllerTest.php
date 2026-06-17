<?php

namespace Tests\Feature;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    private const SECRET = 'test-jwt-secret-longue-au-moins-32-chars';

    protected function setUp(): void
    {
        parent::setUp();
        config(['supabase.jwt_secret' => self::SECRET]);
    }

    private function authHeader(): array
    {
        $token = JWT::encode([
            'sub'   => 'user-uuid-test',
            'email' => 'user@test.com',
            'role'  => 'authenticated',
            'exp'   => time() + 3600,
            'iat'   => time(),
            'aud'   => 'authenticated',
        ], self::SECRET, 'HS256');

        return ['Authorization' => 'Bearer '.$token];
    }

    // --- GET /api/projects ---

    public function test_index_requires_auth(): void
    {
        $this->getJson('/api/projects')->assertStatus(401);
    }

    // --- POST /api/projects ---

    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/projects', ['title' => 'Test', 'type' => 'film'])
             ->assertStatus(401);
    }

    public function test_store_requires_title(): void
    {
        $this->postJson('/api/projects', ['type' => 'film'], $this->authHeader())
             ->assertStatus(422)
             ->assertJsonValidationErrors(['title']);
    }

    public function test_store_requires_type(): void
    {
        $this->postJson('/api/projects', ['title' => 'Mon film'], $this->authHeader())
             ->assertStatus(422)
             ->assertJsonValidationErrors(['type']);
    }

    public function test_store_rejects_invalid_type(): void
    {
        $this->postJson('/api/projects', ['title' => 'Mon film', 'type' => 'invalid'], $this->authHeader())
             ->assertStatus(422)
             ->assertJsonValidationErrors(['type']);
    }

    public function test_store_rejects_empty_title(): void
    {
        $this->postJson('/api/projects', ['title' => '', 'type' => 'film'], $this->authHeader())
             ->assertStatus(422)
             ->assertJsonValidationErrors(['title']);
    }

    // --- GET /api/projects/{id} ---

    public function test_show_requires_auth(): void
    {
        $this->getJson('/api/projects/some-uuid')->assertStatus(401);
    }
}
