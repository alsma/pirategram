<?php

declare(strict_types=1);

namespace App\MatchMaking\Data;

readonly class MatchMakingIdleStateDTO implements MatchMakingState
{
    public function __construct(
        public string $state,
    ) {}
}
