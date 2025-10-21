<?php

declare(strict_types=1);

namespace App\MatchMaking\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

readonly class PartyUpdated implements ShouldBroadcastNow
{
    public function __construct(
        public int $partyId,
        public string $action,
        public array $state = [],
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("party.{$this->partyId}");
    }

    public function broadcastAs(): string
    {
        return 'party.updated';
    }

    public function broadcastWith(): array
    {
        return ['action' => $this->action, 'state' => $this->state];
    }
}
