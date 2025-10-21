<?php

declare(strict_types=1);

namespace App\MatchMaking\ValueObjects;

enum TicketStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Confirmed = 'confirmed';
    case Timeout = 'timeout';
}
