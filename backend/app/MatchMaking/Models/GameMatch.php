<?php

declare(strict_types=1);

namespace App\MatchMaking\Models;

use Illuminate\Database\Eloquent\Model;

class GameMatch extends Model
{
    public function casts(): array
    {
        return [
            'teams' => 'json',
            'players' => 'json',
        ];
    }
}
