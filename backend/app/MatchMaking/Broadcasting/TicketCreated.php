<?php

declare(strict_types=1);

namespace App\MatchMaking\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

readonly class TicketCreated implements ShouldBroadcastNow
{
    public function __construct(
        public string $ticketId,
        public string $mode,
        public array $teams,
        public int $expiresAt
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("ticket.{$this->ticketId}");
    }

    public function broadcastAs(): string
    {
        return 'ticket.created';
    }

    public function broadcastWith(): array
    {
        return [
            'ticketId' => $this->ticketId,
            'mode' => $this->mode,
            'teams' => $this->teams,
            'expiresAt' => $this->expiresAt,
        ];
    }
}
