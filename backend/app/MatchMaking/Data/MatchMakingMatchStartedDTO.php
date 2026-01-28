<?php

declare(strict_types=1);

namespace App\MatchMaking\Data;

readonly class MatchMakingMatchStartedDTO
{
    public function __construct(
        public int $matchId,
    ) {}
}
