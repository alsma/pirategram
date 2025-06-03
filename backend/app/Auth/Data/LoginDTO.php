<?php

declare(strict_types=1);

namespace App\Auth\Data;

readonly class LoginDTO
{
    public function __construct(
        public string $identity,
        public string $password,
    ) {}
}
