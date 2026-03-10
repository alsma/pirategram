<?php

declare(strict_types=1);

namespace App\Social\ValueObjects;

enum FriendshipStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Blocked = 'blocked';
}
