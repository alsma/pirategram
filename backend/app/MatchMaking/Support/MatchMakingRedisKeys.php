<?php

declare(strict_types=1);

namespace App\MatchMaking\Support;

final class MatchMakingRedisKeys
{
    public const array QUEUE_KEYS = [
        '1v1' => 'mm:queue:1v1',
        '2v2' => 'mm:queue:2v2',
        'ffa4' => 'mm:queue:ffa4',
    ];

    public const string GROUP_KEY_PREFIX = 'mm:group:';

    public const string TICKET_KEY_PREFIX = 'mm:ticket:';

    public const string ACTIVE_SESSION_PREFIX = 'mm:active_session:user:';
}
