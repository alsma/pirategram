<?php

declare(strict_types=1);

use App\User\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{hash}', function (User $user, $userHash) {
    return $user->getHashedId() === $userHash;
});
