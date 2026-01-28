<?php

declare(strict_types=1);

namespace App\MatchMaking\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

readonly class MMTicketExpired implements ShouldBroadcastNow
{
    public function __construct(
        public string $userHash,
        public string $ticketId,
        public string $reason,
        public bool $backToSearch,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->userHash}");
    }

    public function broadcastAs(): string
    {
        return 'mm.ticket.expired';
    }

    public function broadcastWith(): array
    {
        return [
            'ticketId' => $this->ticketId,
            'reason' => $this->reason,
            'backToSearch' => $this->backToSearch,
        ];
    }
}
