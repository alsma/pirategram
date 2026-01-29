<?php

declare(strict_types=1);

namespace App\MatchMaking\ValueObjects;

enum PartyAction: string
{
    case Created = 'created';
    case Disbanded = 'disbanded';
    case MemberJoined = 'memberJoined';
    case MemberLeft = 'memberLeft';
    case MemberKicked = 'memberKicked';
    case LeaderChanged = 'leaderChanged';
    case ModeChanged = 'modeChanged';
}
