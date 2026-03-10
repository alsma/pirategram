<?php

declare(strict_types=1);

namespace App\Social\ValueObjects;

enum FriendAction: string
{
    case RequestSent = 'requestSent';
    case RequestAccepted = 'requestAccepted';
    case RequestDeclined = 'requestDeclined';
    case FriendRemoved = 'friendRemoved';
    case StatusChanged = 'statusChanged';
}
