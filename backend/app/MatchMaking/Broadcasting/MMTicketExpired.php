<?php

declare(strict_types=1);

namespace App\MatchMaking\Broadcasting;

use App\MatchMaking\Data\MatchMakingTicketExpiredDTO;
use App\MatchMaking\Http\Resources\MatchMakingTicketExpiredResource;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

readonly class MMTicketExpired implements ShouldBroadcastNow
{
    public function __construct(
        public string $userHash,
        public MatchMakingTicketExpiredDTO $payload,
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
        return MatchMakingTicketExpiredResource::make($this->payload)->resolve();
    }
}
