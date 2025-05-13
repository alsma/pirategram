<?php

declare(strict_types=1);

namespace App\Game\Data;

enum EntityType: string
{
    case Pirate = 'pirate';
    case Coin = 'coin';
    case Ship = 'ship';
    case Null = 'null';
}
