<?php

declare(strict_types=1);

namespace App\MatchMaking\Jobs;

use App\MatchMaking\MatchMakingManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TicketExpiryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $ticketId,
    ) {}

    public function handle(MatchMakingManager $mm): void
    {
        $mm->expireTicket($this->ticketId);
    }
}
