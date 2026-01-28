<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MatchMakingOkResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['ok' => true];
    }
}
