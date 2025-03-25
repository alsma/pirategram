<?php

declare(strict_types=1);

namespace App\Game\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait GameStateTrait
{
    public function gameState(): BelongsTo
    {
        return $this->belongsTo(GameState::class);
    }
}
