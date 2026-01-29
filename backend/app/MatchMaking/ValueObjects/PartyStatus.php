<?php

declare(strict_types=1);

namespace App\MatchMaking\ValueObjects;

enum PartyStatus: string
{
    case Idle = 'idle';
    case Searching = 'searching';
}
