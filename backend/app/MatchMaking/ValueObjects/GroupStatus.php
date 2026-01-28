<?php

declare(strict_types=1);

namespace App\MatchMaking\ValueObjects;

enum GroupStatus: string
{
    case Idle = 'idle';
    case Searching = 'searching';
    case Proposed = 'proposed';
    case Starting = 'starting';
    case InMatch = 'inMatch';
}
