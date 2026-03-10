<?php

declare(strict_types=1);

namespace App\Social\Listeners;

use App\Auth\Events\UserLoggedOut;
use App\Social\FriendshipManager;
use App\Social\ValueObjects\UserPresenceStatus;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class SetUserOfflineOnLogoutListener
{
    public function __construct(
        private readonly FriendshipManager $friendshipManager,
    ) {}

    public function handle(UserLoggedOut $event): void
    {
        $this->friendshipManager->setUserPresence($event->user->id, UserPresenceStatus::Offline);
    }
}
