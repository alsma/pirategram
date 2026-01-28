<?php

declare(strict_types=1);

namespace App\MatchMaking\Data;

readonly class MatchMakingTicketCreatedDTO
{
    public function __construct(
        public string $ticketId,
        public string $mode,
        public int $readyExpiresAt,
        public int $slotsTotal,
        public array $slots,
        public int $yourSlot,
    ) {}
}
