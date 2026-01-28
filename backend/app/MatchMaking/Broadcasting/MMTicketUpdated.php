<?php

declare(strict_types=1);

namespace App\MatchMaking\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

readonly class MMTicketUpdated implements ShouldBroadcastNow
{
    public function __construct(
        public string $userHash,
        public string $ticketId,
        public array $updates,
        public int $acceptedCount,
        public int $declinedCount,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->userHash}");
    }

    public function broadcastAs(): string
    {
        return 'mm.ticket.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'ticketId' => $this->ticketId,
            'updates' => $this->updates,
            'acceptedCount' => $this->acceptedCount,
            'declinedCount' => $this->declinedCount,
        ];
    }
}
