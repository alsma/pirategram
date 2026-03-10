<?php

declare(strict_types=1);

namespace App\Social\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

readonly class FriendStatusChanged implements ShouldBroadcastNow
{
    public function __construct(
        public string $recipientHash,
        public string $friendHash,
        public string $status,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->recipientHash}");
    }

    public function broadcastAs(): string
    {
        return 'friend.status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'friendHash' => $this->friendHash,
            'status' => $this->status,
        ];
    }
}
