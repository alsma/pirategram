<?php

declare(strict_types=1);

namespace App\MatchMaking\Data;

readonly class MatchMakingSearchUpdateDTO
{
    public function __construct(
        public string $state,
        public ?string $mode = null,
        public ?int $searchStartedAt = null,
        public ?int $searchExpiresAt = null,
        public ?string $reason = null,
    ) {}
}
