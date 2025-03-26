<?php

declare(strict_types=1);

namespace App\Game\Support;

use App\Exceptions\RuntimeException;
use App\Game\Behaviors\CellBehavior;
use App\Game\Behaviors\EntityBehavior;
use App\Game\Behaviors\NullCellBehavior;
use App\Game\Behaviors\NullEntityBehavior;
use App\Game\Data\CellType;
use App\Game\Data\EntityType;

trait BehaviorsAwareTrait
{
    private array $cellBehaviors = [];

    private array $entityBehaviors = [];

    private ?\Closure $behaviorResolver = null;

    public function registerCellBehavior(CellType $cellType, string $className): void
    {
        if (!is_a($className, CellBehavior::class, true)) {
            throw new RuntimeException("Class {$className} must be an instance of CellBehavior");
        }

        $this->cellBehaviors[$cellType->value] = $className;
    }

    public function registerEntityBehavior(EntityType $entityType, string $className): void
    {
        if (!is_a($className, EntityBehavior::class, true)) {
            throw new RuntimeException("Class {$className} must be an instance of EntityBehavior");
        }

        $this->entityBehaviors[$entityType->value] = $className;
    }

    public function setBehaviorResolver(\Closure $resolver): void
    {
        $this->behaviorResolver = $resolver;
    }

    public function getCellBehavior(CellType $cellType): CellBehavior
    {
        if (!$this->behaviorResolver) {
            throw new RuntimeException('No behavior resolver available.');
        }

        $behavior = $this->cellBehaviors[$cellType->value] ?? NullCellBehavior::class;

        return call_user_func($this->behaviorResolver, $behavior);
    }

    public function getEntityBehavior(EntityType $entityType): EntityBehavior
    {
        if (!$this->behaviorResolver) {
            throw new RuntimeException('No behavior resolver available.');
        }

        $behavior = $this->entityBehaviors[$entityType->value] ?? NullEntityBehavior::class;

        return call_user_func($this->behaviorResolver, $behavior);
    }
}
