<?php

declare(strict_types=1);

namespace App\MatchMaking\ValueObjects;

enum CancelReason: string
{
    case UserCancelled = 'USER_CANCELLED';
    case Declined = 'DECLINED';
    case Timeout = 'TIMEOUT';
    case SearchTimeout = 'SEARCH_TIMEOUT';
}
