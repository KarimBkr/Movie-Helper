<?php

namespace App\DTOs;

readonly class AuthUser
{
    public function __construct(
        public string $id,
        public string $email,
    ) {}
}
