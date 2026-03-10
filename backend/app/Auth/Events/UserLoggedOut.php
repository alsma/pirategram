<?php

declare(strict_types=1);

namespace App\Auth\Events;

use App\User\Events\BaseUserEvent;

class UserLoggedOut extends BaseUserEvent {}
