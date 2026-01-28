<?php

declare(strict_types=1);

namespace App\MatchMaking\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

readonly class MMTicketCreated implements ShouldBroadcastNow
{
    public function __construct(
        public string $userHash,
        public string $ticketId,
        public string $mode,
        public int $readyExpiresAt,
        public int $slotsTotal,
        public array $slots,
        public int $yourSlot,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->userHash}");
    }

    public function broadcastAs(): string
    {
        return 'mm.ticket.created';
    }

    public function broadcastWith(): array
    {
        return [
            'ticketId' => $this->ticketId,
            'mode' => $this->mode,
            'readyExpiresAt' => $this->readyExpiresAt,
            'slotsTotal' => $this->slotsTotal,
            'slots' => $this->slots,
            'yourSlot' => $this->yourSlot,
        ];
    }
}
