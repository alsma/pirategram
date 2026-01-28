<?php

declare(strict_types=1);

namespace App\MatchMaking\Data;

readonly class MatchMakingProposedStateDTO implements MatchMakingState
{
    /** @param array<int, array<string, mixed>> $slots */
    public function __construct(
        public string $state,
        public string $mode,
        public string $ticketId,
        public int $readyExpiresAt,
        public array $slots,
        public int $yourSlot,
    ) {}
}
