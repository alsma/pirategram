<?php

declare(strict_types=1);

namespace App\MatchMaking\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

readonly class PartyInviteDeclined implements ShouldBroadcastNow
{
    public function __construct(
        public string $leaderHash,
        public string $userHash,
        public string $username,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->leaderHash}");
    }

    public function broadcastAs(): string
    {
        return 'party.invite.declined';
    }

    public function broadcastWith(): array
    {
        return [
            'userHash' => $this->userHash,
            'username' => $this->username,
        ];
    }
}
