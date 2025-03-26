<?php

declare(strict_types=1);

namespace App\Game\Data;

use Illuminate\Support\Collection;

class EntityCollection extends Collection
{
    public function updateEntities(Collection $entities): self
    {
        $updatedEntitiesById = $entities->keyBy('id');

        return $this->map(fn (Entity $entity) => $updatedEntitiesById[$entity->id] ?? $entity);
    }
}
