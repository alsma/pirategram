<?php

declare(strict_types=1);

namespace App\MatchMaking\Broadcasting;

use App\MatchMaking\ValueObjects\GroupStatus;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

readonly class MMSearchUpdated implements ShouldBroadcastNow
{
    public function __construct(
        public string $userHash,
        public GroupStatus $state,
        public ?string $mode = null,
        public ?int $searchStartedAt = null,
        public ?int $searchExpiresAt = null,
        public ?string $reason = null,
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
        return array_filter([
            'state' => $this->state->value,
            'mode' => $this->mode,
            'searchStartedAt' => $this->searchStartedAt,
            'searchExpiresAt' => $this->searchExpiresAt,
            'reason' => $this->reason,
        ], fn ($v) => $v !== null);
    }
}
