<?php

declare(strict_types=1);

namespace App\Auth\Data;

readonly class RegisterDTO
{
    public function __construct(
        public string $email,
        public string $language,
        public array $options,
    ) {}
}
