<?php

declare(strict_types=1);

namespace App\MatchMaking\Data;

readonly class MatchMakingStartDTO
{
    public function __construct(
        public string $state,
        public string $mode,
        public int $searchStartedAt,
        public int $searchExpiresAt,
        public string $sessionId,
    ) {}
}
