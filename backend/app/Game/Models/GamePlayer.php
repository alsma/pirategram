<?php

declare(strict_types=1);

namespace App\Game\Models;

use App\Support\Models\HashedIdTrait;
use App\User\Models\UserTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GamePlayer extends Model
{
    use GameStateTrait, HasFactory, HashedIdTrait, UserTrait;

    public $timestamps = false;
}
