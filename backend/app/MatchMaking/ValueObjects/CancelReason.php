<?php

declare(strict_types=1);

namespace App\MatchMaking\ValueObjects;

enum CancelReason: string
{
    case UserCancelled = 'userCancelled';
    case Declined = 'declined';
    case Timeout = 'timeout';
    case SearchTimeout = 'searchTimeout';
}
