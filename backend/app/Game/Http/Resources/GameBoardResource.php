<?php

declare(strict_types=1);

namespace App\Game\Http\Resources;

use App\Game\Data\Cell;
use App\Game\Data\GameBoard;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property GameBoard $resource */
class GameBoardResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'cells' => collect($this->resource->getCells())
                ->map(function ($row) {
                    return collect($row)
                        ->map(function (?Cell $cell) {
                            return $cell ? CellResource::make($cell) : null;
                        });
                })
                ->all(),
        ];
    }
}
