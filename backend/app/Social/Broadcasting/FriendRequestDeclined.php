<?php

declare(strict_types=1);

namespace App\Social\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

readonly class FriendRequestDeclined implements ShouldBroadcastNow
{
    public function __construct(
        public string $requesterHash,
        public string $declinerHash,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->requesterHash}");
    }

    public function broadcastAs(): string
    {
        return 'friend.request.declined';
    }

    public function broadcastWith(): array
    {
        return [
            'declinerHash' => $this->declinerHash,
        ];
    }
}
