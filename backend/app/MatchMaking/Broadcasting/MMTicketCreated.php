<?php

declare(strict_types=1);

namespace App\MatchMaking\Broadcasting;

use App\MatchMaking\Data\MatchMakingTicketCreatedDTO;
use App\MatchMaking\Http\Resources\MatchMakingTicketCreatedResource;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

readonly class MMTicketCreated implements ShouldBroadcastNow
{
    public function __construct(
        public string $userHash,
        public MatchMakingTicketCreatedDTO $payload,
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
        return MatchMakingTicketCreatedResource::make($this->payload)->resolve();
    }
}
