<?php

declare(strict_types=1);

namespace App\Game\Data;

use Illuminate\Support\Collection;

class EntityCollection extends Collection
{
    public function updateEntity(Entity $entity): self
    {
        return $this->updateEntities(collect([$entity]));
    }

    public function updateEntities(Collection $entities): self
    {
        $updatedEntitiesById = $entities->keyBy('id');

        return $this->map(fn (Entity $entity) => $updatedEntitiesById[$entity->id] ?? $entity);
    }

    public function getEntityByIdOrFail(string $id): Entity
    {
        return $this->firstOrFail('id', $id);
    }
}
