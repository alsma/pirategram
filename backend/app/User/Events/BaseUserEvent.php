<?php

declare(strict_types=1);

namespace App\User\Events;

use App\User\Models\User;

abstract class BaseUserEvent
{
    public function __construct(public readonly User $user) {}
}
