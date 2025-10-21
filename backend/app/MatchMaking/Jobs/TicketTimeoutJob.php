<?php

declare(strict_types=1);

namespace App\MatchMaking\Jobs;

use App\MatchMaking\MatchMakingManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class TicketTimeoutJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $ticketId) {}

    public function handle(MatchMakingManager $manager): void
    {
        $manager->timeoutTicket($this->ticketId);
    }

    public function tags(): array
    {
        return [
            'matchmaking',
            'matchmaking:ticket-timeout',
            "matchmaking:ticket-timeout:{$this->ticketId}",
        ];
    }
}
