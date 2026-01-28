<?php

declare(strict_types=1);

namespace App\MatchMaking\Data;

readonly class MatchMakingTicketExpiredDTO
{
    public function __construct(
        public string $ticketId,
        public string $reason,
        public bool $backToSearch,
    ) {}
}
