<?php

declare(strict_types=1);

namespace App\MatchMaking\Events;

class MatchStarted
{
    /**
     * @param  array<int, int>  $playerUserIds
     */
    public function __construct(
        public readonly array $playerUserIds,
        public readonly int $matchId,
    ) {}
}
