<?php

declare(strict_types=1);

namespace App\Social\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FriendRequestsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'incoming' => $this->resource['incoming'],
            'outgoing' => $this->resource['outgoing'],
        ];
    }
}
