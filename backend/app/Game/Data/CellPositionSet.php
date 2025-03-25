<?php

declare(strict_types=1);

namespace App\Game\Data;

class CellPositionSet
{
    /** @var array<string, CellPosition> */
    private array $cellPositionIndex = [];

    public function add(CellPosition $cellPosition): self
    {
        $this->cellPositionIndex[$this->getCellPositionIndexKey($cellPosition)] = $cellPosition;

        return $this;
    }

    public function exists(CellPosition $cellPosition): bool
    {
        return isset($this->cellPositionIndex[$this->getCellPositionIndexKey($cellPosition)]);
    }

    private function getCellPositionIndexKey(CellPosition $cellPosition): string
    {
        return "c:{$cellPosition->col}:{$cellPosition->row}";
    }
}
