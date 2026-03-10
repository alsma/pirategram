<?php

declare(strict_types=1);

namespace App\Social\Listeners;

use App\MatchMaking\Events\MatchStarted;
use App\Social\FriendshipManager;
use App\Social\ValueObjects\UserPresenceStatus;

class SetUsersInGameOnMatchStartListener
{
    public function __construct(
        private readonly FriendshipManager $friendshipManager,
    ) {}

    public function handle(MatchStarted $event): void
    {
        foreach ($event->playerUserIds as $userId) {
            $this->friendshipManager->setUserPresence($userId, UserPresenceStatus::InGame);
        }
    }
}
