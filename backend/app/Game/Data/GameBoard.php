<?php

declare(strict_types=1);

namespace App\Game\Data;

use Illuminate\Contracts\Support\Arrayable;

class GameBoard implements Arrayable
{
    public array $cells;

    public function __construct(
        private readonly int $rows,
        private readonly int $cols,
    ) {
        $this->cells = array_fill(0, $this->rows, array_fill(0, $this->cols, null));
    }

    public static function fromArray(array $board): self
    {
        $instance = new self($board['rows'], $board['cols']);
        foreach ($board['cells'] as $cell) {
            $instance->setCell(new CellPosition($cell['col'], $cell['row']), Cell::fromArray(array_except($cell, ['row', 'col'])));
        }

        return $instance;
    }

    public function setCell(CellPosition $position, Cell $cell): void
    {
        $this->cells[$position->row][$position->col] = $cell;
    }

    public function getCell(CellPosition $position): ?Cell
    {
        return $this->cells[$position->row][$position->col] ?? null;
    }

    public function hasCell(CellPosition $position): bool
    {
        return isset($this->cells[$position->row][$position->col]);
    }

    public function pushCell(Cell $newCell): void
    {
        for ($row = 0; $row < $this->rows; $row++) {
            for ($col = 0; $col < $this->cols; $col++) {
                if (isset($this->cells[$row][$col])) {
                    continue;
                }

                $this->cells[$row][$col] = $newCell;
                break 2;
            }
        }
    }

    public function toArray(): array
    {
        return [
            'rows' => $this->rows,
            'cols' => $this->cols,
            'cells' => collect($this->cells)
                ->flatMap(function ($row, $rowIndex) {
                    return collect($row)
                        ->map(function (?Cell $cell, $colIndex) use ($rowIndex) {
                            return $cell ? array_merge($cell->toArray(), [
                                'row' => $rowIndex,
                                'col' => $colIndex,
                            ]) : null;
                        });
                })
                ->filter() // Remove null values (for empty cells)
                ->values() // Reindex the array
                ->all(),
        ];
    }
}
