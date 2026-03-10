<?php

declare(strict_types=1);

namespace App\Social\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

readonly class FriendRequestAccepted implements ShouldBroadcastNow
{
    public function __construct(
        public string $recipientHash,
        public string $friendHash,
        public string $friendUsername,
        public string $friendsSince,
        public string $friendStatus,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->recipientHash}");
    }

    public function broadcastAs(): string
    {
        return 'friend.request.accepted';
    }

    public function broadcastWith(): array
    {
        return [
            'userHash' => $this->friendHash,
            'username' => $this->friendUsername,
            'friendsSince' => $this->friendsSince,
            'status' => $this->friendStatus,
        ];
    }
}
