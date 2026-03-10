<?php

declare(strict_types=1);

namespace App\Social\ValueObjects;

enum UserPresenceStatus: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Away = 'away';
    case InGame = 'in-game';
}
