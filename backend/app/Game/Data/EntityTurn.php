<?php

declare(strict_types=1);

namespace App\Game\Data;

readonly class EntityTurn
{
    public function __construct(
        public string $entityId,
        public Cell $cell,
        public CellPosition $position,
        /** @var string[] */
        public array $canCarry = [],
    ) {}

    public function canCarry(string $entityId): bool
    {
        return in_array($entityId, $this->canCarry, true);
    }

    public function allowCarry(array|string $entityTypes): self
    {
        if (!is_array($entityTypes)) {
            $entityTypes = [$entityTypes];
        }

        return new self($this->entityId, $this->cell, $this->position, array_unique(array_merge($this->canCarry, $entityTypes)));
    }
}
