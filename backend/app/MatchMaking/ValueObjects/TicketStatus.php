<?php

declare(strict_types=1);

namespace App\MatchMaking\ValueObjects;

enum TicketStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Started = 'started';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
