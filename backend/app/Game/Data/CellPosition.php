<?php

declare(strict_types=1);

namespace App\Game\Data;

use Illuminate\Contracts\Support\Arrayable;

readonly class CellPosition implements \Stringable, Arrayable
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

    public function toArray(): array
    {
        return [
            'col' => $this->col,
            'row' => $this->row,
        ];
    }

    public function __toString(): string
    {
        return "{$this->col} {$this->row}";
    }
}
