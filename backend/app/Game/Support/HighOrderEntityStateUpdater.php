<?php

declare(strict_types=1);

namespace App\Game\Support;

use App\Game\Data\Entity;

/**
 * @method Entity set(string $key, mixed $value)
 * @method Entity unset(string $key)
 * @method Entity increment(string $key, int $incrementBy = 1, int $default = 0)
 * @method Entity decrement(string $key, int $decrementBy = 1, int $default = 0)
 */
readonly class HighOrderEntityStateUpdater
{
    public function __construct(
        private Entity $entity,
    ) {}

    public function __call(string $name, array $arguments): Entity
    {
        $updatedState = $this->entity->state->{$name}(...$arguments);

        return $this->entity->updateState($updatedState);
    }
}
