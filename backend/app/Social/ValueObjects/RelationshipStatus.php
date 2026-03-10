<?php

declare(strict_types=1);

namespace App\Social\ValueObjects;

enum RelationshipStatus: string
{
    case None = 'none';
    case Friends = 'friends';
    case RequestSent = 'request_sent';
    case RequestReceived = 'request_received';
}
