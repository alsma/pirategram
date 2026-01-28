<?php

declare(strict_types=1);

namespace App\MatchMaking\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

readonly class MMStarting implements ShouldBroadcastNow
{
    public function __construct(
        public string $userHash,
        public string $ticketId,
        public int $startAt,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->userHash}");
    }

    public function broadcastAs(): string
    {
        return 'mm.starting';
    }

    public function broadcastWith(): array
    {
        return [
            'ticketId' => $this->ticketId,
            'startAt' => $this->startAt,
        ];
    }
}
