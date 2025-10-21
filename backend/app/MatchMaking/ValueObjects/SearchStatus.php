<?php

declare(strict_types=1);

namespace App\MatchMaking\ValueObjects;

enum SearchStatus: string
{
    case Idle = 'idle';
    case Searching = 'started';
    case Cancelled = 'cancelled';
    case Matched = 'matched';
}
