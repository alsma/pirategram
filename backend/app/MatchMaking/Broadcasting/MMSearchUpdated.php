<?php

declare(strict_types=1);

namespace App\MatchMaking\Broadcasting;

use App\MatchMaking\Data\MatchMakingSearchUpdateDTO;
use App\MatchMaking\Http\Resources\MatchMakingSearchUpdateResource;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

readonly class MMSearchUpdated implements ShouldBroadcastNow
{
    public function __construct(
        public string $userHash,
        public MatchMakingSearchUpdateDTO $payload,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->userHash}");
    }

    public function broadcastAs(): string
    {
        return 'mm.search.updated';
    }

    public function broadcastWith(): array
    {
        return MatchMakingSearchUpdateResource::make($this->payload)->resolve();
    }
}
