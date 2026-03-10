<?php

declare(strict_types=1);

namespace App\Social\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

readonly class FriendRequestReceived implements ShouldBroadcastNow
{
    public function __construct(
        public string $recipientHash,
        public string $requesterHash,
        public string $requesterUsername,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->recipientHash}");
    }

    public function broadcastAs(): string
    {
        return 'friend.request.received';
    }

    public function broadcastWith(): array
    {
        return [
            'requesterHash' => $this->requesterHash,
            'username' => $this->requesterUsername,
        ];
    }
}
