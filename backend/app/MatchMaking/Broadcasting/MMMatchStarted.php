<?php

declare(strict_types=1);

namespace App\MatchMaking\Broadcasting;

use App\MatchMaking\Data\MatchMakingMatchStartedDTO;
use App\MatchMaking\Http\Resources\MatchMakingMatchStartedResource;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

readonly class MMMatchStarted implements ShouldBroadcastNow
{
    public function __construct(
        public string $userHash,
        public MatchMakingMatchStartedDTO $payload,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->userHash}");
    }

    public function broadcastAs(): string
    {
        return 'mm.match.started';
    }

    public function broadcastWith(): array
    {
        return MatchMakingMatchStartedResource::make($this->payload)->resolve();
    }
}
