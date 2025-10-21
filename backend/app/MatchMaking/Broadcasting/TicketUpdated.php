<?php

declare(strict_types=1);

namespace App\MatchMaking\Broadcasting;

use App\MatchMaking\ValueObjects\TicketStatus;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

readonly class TicketUpdated implements ShouldBroadcastNow
{
    public function __construct(
        public string $ticketId,
        public TicketStatus $status,
        public ?int $userId = null,
        public array $extra = []
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("ticket.{$this->ticketId}");
    }

    public function broadcastAs(): string
    {
        return 'ticket.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'ticketId' => $this->ticketId,
            'status' => $this->status->value,
            'userId' => $this->userId,
            'extra' => $this->extra,
        ];
    }
}
