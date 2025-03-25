<?php

declare(strict_types=1);

namespace App\User\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait UserTrait
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
