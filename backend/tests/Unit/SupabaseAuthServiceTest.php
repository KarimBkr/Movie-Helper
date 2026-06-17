<?php

namespace Tests\Unit;

use App\DTOs\AuthUser;
use App\Services\SupabaseAuthService;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SupabaseAuthServiceTest extends TestCase
{
    private const SECRET = 'test-jwt-secret-longue-au-moins-32-chars';

    private function makeService(string $secret = self::SECRET): SupabaseAuthService
    {
        return new SupabaseAuthService($secret);
    }

    private function makeToken(array $overrides = []): string
    {
        $payload = array_merge([
            'sub'   => 'uuid-1234-abcd',
            'email' => 'user@test.com',
            'role'  => 'authenticated',
            'exp'   => time() + 3600,
            'iat'   => time(),
            'aud'   => 'authenticated',
        ], $overrides);

        return JWT::encode($payload, self::SECRET, 'HS256');
    }

    public function test_decode_valid_token_returns_auth_user(): void
    {
        $user = $this->makeService()->decodeToken($this->makeToken());

        $this->assertInstanceOf(AuthUser::class, $user);
        $this->assertSame('uuid-1234-abcd', $user->id);
        $this->assertSame('user@test.com', $user->email);
    }

    public function test_decode_expired_token_throws(): void
    {
        $this->expectException(\Throwable::class);
        $this->makeService()->decodeToken($this->makeToken(['exp' => time() - 10]));
    }

    public function test_decode_wrong_signature_throws(): void
    {
        $this->expectException(\Throwable::class);
        $this->makeService()->decodeToken($this->makeToken().'tampered');
    }

    public function test_decode_token_missing_email_throws(): void
    {
        $token = JWT::encode(['sub' => 'uuid-1234', 'exp' => time() + 3600], self::SECRET, 'HS256');

        $this->expectException(RuntimeException::class);
        $this->makeService()->decodeToken($token);
    }

    public function test_decode_empty_secret_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->makeService('')->decodeToken($this->makeToken());
    }
}
