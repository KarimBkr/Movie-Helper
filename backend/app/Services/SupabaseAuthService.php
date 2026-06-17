<?php

namespace App\Services;

use App\DTOs\AuthUser;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;

class SupabaseAuthService
{
    public function __construct(private readonly string $jwtSecret) {}

    public function decodeToken(string $token): AuthUser
    {
        if (empty($this->jwtSecret)) {
            throw new RuntimeException('SUPABASE_JWT_SECRET non configuré.');
        }

        $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));

        if (empty($decoded->sub) || empty($decoded->email)) {
            throw new RuntimeException('Token invalide : champs manquants.');
        }

        return new AuthUser(
            id: $decoded->sub,
            email: $decoded->email,
        );
    }
}
