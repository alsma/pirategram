<?php

declare(strict_types=1);

namespace App\MatchMaking\Broadcasting;

use App\MatchMaking\Data\MatchMakingTicketUpdatedDTO;
use App\MatchMaking\Http\Resources\MatchMakingTicketUpdatedResource;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

readonly class MMTicketUpdated implements ShouldBroadcastNow
{
    public function __construct(
        public string $userHash,
        public MatchMakingTicketUpdatedDTO $payload,
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
        return MatchMakingTicketUpdatedResource::make($this->payload)->resolve();
    }
}
