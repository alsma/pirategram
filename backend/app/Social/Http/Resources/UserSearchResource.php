<?php

declare(strict_types=1);

namespace App\Social\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserSearchResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'results' => $this->resource,
        ];
    }
}
