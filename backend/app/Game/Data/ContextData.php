<?php

declare(strict_types=1);

namespace App\Game\Data;

use App\Exceptions\RuntimeException;

readonly class ContextData
{
    public function __construct(private array $data) {}

    public function mustGet(string $key)
    {
        return $this->data[$key] ?? throw new RuntimeException("Context value '{$key}' must be filled.");
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function merge(ContextData $contextData): self
    {
        return new self(array_merge($this->data, $contextData->data));
    }
}
