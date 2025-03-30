<?php

declare(strict_types=1);

namespace App\Game\Http\Resources;

use App\Game\Data\Cell;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property Cell $resource */
class CellResource extends JsonResource
{
    public function toArray($request): array
    {
        // Uncomment to see the map
        // $this->resource = $this->resource->reveal();

        return [
            $this->mergeWhen($this->resource->revealed, fn () => [
                'type' => $this->resource->type,
                'direction' => $this->resource->direction,
            ]),
            'revealed' => $this->resource->revealed,
        ];
    }
}
