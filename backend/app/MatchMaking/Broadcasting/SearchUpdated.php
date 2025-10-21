<?php

declare(strict_types=1);

namespace App\MatchMaking\Broadcasting;

use App\MatchMaking\ValueObjects\SearchStatus;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

readonly class SearchUpdated implements ShouldBroadcastNow
{
    public function __construct(
        public int $partyId,
        public SearchStatus $status,
        public array $payload = []
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("party.{$this->partyId}");
    }

    public function broadcastAs(): string
    {
        return 'search.updated';
    }

    public function broadcastWith(): array
    {
        return ['status' => $this->status->value, 'data' => $this->payload];
    }
}
