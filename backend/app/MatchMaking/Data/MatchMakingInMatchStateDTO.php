<?php

declare(strict_types=1);

namespace App\MatchMaking\Data;

readonly class MatchMakingInMatchStateDTO implements MatchMakingState
{
    public function __construct(
        public string $state,
        public int $matchId,
    ) {}
}
