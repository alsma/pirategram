<?php

declare(strict_types=1);

namespace App\MatchMaking\Data;

readonly class MatchMakingTicketUpdatedDTO
{
    public function __construct(
        public string $ticketId,
        public array $updates,
        public int $acceptedCount,
        public int $declinedCount,
    ) {}
}
