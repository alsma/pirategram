<?php

declare(strict_types=1);

namespace App\MatchMaking\Data;

readonly class MatchMakingMatchStartingDTO
{
    public function __construct(
        public string $ticketId,
        public int $startAt,
    ) {}
}
