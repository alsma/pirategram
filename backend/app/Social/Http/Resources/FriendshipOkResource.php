<?php

declare(strict_types=1);

namespace App\Social\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FriendshipOkResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['ok' => true];
    }
}
