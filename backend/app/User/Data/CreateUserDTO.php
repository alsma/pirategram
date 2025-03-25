<?php

declare(strict_types=1);

namespace App\User\Data;

readonly class CreateUserDTO
{
    public function __construct(
        public string $username,
        public string $email,
        public ?string $password,
        public string $language,
    ) {}
}
