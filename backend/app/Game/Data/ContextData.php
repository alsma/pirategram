<?php

declare(strict_types=1);

namespace App\Game\Data;

use App\Exceptions\RuntimeException;

readonly class Context
{
    public function __construct(private array $data) {}

    public function mustGet(string $key)
    {
        return $this->data[$key] ?? throw new RuntimeException("Context value '{$key}' must be filled.");
    }
}
