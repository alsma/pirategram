<?php

declare(strict_types=1);

namespace App\MatchMaking\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

readonly class PartyInviteCreated implements ShouldBroadcastNow
{
    public function __construct(
        public string $userHash,
        public string $leaderHash,
        public string $leaderUsername,
        public string $mode,
        public ?string $partyHash = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->userHash}");
    }

    public function broadcastAs(): string
    {
        return 'party.invite.created';
    }

    public function broadcastWith(): array
    {
        return [
            'leaderHash' => $this->leaderHash,
            'leaderUsername' => $this->leaderUsername,
            'mode' => $this->mode,
            'partyHash' => $this->partyHash,
        ];
    }
}
