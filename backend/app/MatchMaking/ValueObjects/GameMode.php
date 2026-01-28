<?php

declare(strict_types=1);

namespace App\MatchMaking\ValueObjects;

enum GameMode: string
{
    case OneOnOne = '1v1';
    case TwoVsTwo = '2v2';
    case FreeForAll4 = 'ffa4';
}
