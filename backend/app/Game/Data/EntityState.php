<?php

declare(strict_types=1);

namespace App\Game\Data;

use Illuminate\Contracts\Support\Arrayable;

class EntityState implements Arrayable
{
    public function __construct(
        private array $state = [],
    ) {}

    public function set(string $key, mixed $value): self
    {
        return new self(array_merge($this->state, [(string) $key => $value]));
    }

    public function increment(string $key, int $incrementBy = 1, int $default = 0): self
    {
        return $this->set($key, $this->int($key, $default) + $incrementBy);
    }

    public function decrement(string $key, int $decrementBy = 1, int $default = 0): self
    {
        return $this->increment($key, $decrementBy * -1, $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        return (bool) ($this->state[$key] ?? $default);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) ($this->state[$key] ?? $default);
    }

    public function unset(string $key): self
    {
        return new self(array_except($this->state, $key));
    }

    public function toArray(): array
    {
        return $this->state;
    }
}
