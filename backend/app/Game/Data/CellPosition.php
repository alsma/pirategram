<?php

declare(strict_types=1);

namespace App\Game\Data;

readonly class CellPosition
{
    public function __construct(
        public int $col,
        public int $row,
    ) {}

    public function add(Vector $vector): self
    {
        return new self($this->col + $vector->col, $this->row + $vector->row);
    }

    public function is(CellPosition $position): bool
    {
        return $this->col === $position->col && $this->row === $position->row;
    }

    public function difference(CellPosition $other): Vector
    {
        return new Vector($this->col - $other->col, $this->row - $other->row);
    }
}
