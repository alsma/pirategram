<?php

declare(strict_types=1);

namespace App\User\Http\Resources;

use App\User\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property User $resource */
class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'username' => $this->resource->username,
        ];
    }
}
