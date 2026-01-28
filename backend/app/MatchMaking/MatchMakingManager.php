<?php

declare(strict_types=1);

namespace App\MatchMaking;

use App\MatchMaking\Broadcasting\MMMatchStarted;
use App\MatchMaking\Broadcasting\MMSearchUpdated;
use App\MatchMaking\Broadcasting\MMStarting;
use App\MatchMaking\Broadcasting\MMTicketCreated;
use App\MatchMaking\Broadcasting\MMTicketExpired;
use App\MatchMaking\Broadcasting\MMTicketUpdated;
use App\MatchMaking\Jobs\MatchStartJob;
use App\MatchMaking\Jobs\TicketExpiryJob;
use App\MatchMaking\Models\GameMatch;
use App\MatchMaking\ValueObjects\CancelReason;
use App\MatchMaking\ValueObjects\GameMode;
use App\MatchMaking\ValueObjects\GroupStatus;
use App\MatchMaking\ValueObjects\SlotStatus;
use App\MatchMaking\ValueObjects\TicketStatus;
use App\User\Models\User;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MatchMakingManager
{
    public const array QUEUE_KEYS = [
        '1v1' => 'mm:queue:1v1',
        '2v2' => 'mm:queue:2v2',
        'ffa4' => 'mm:queue:ffa4',
    ];

    public const string GROUP_KEY_PREFIX = 'mm:group:';

    public const string TICKET_KEY_PREFIX = 'mm:ticket:';

    public const string ACTIVE_SESSION_PREFIX = 'mm:active_session:user:';

    public const int SEARCH_TIMEOUT_SECONDS = 600;

    public const int READY_TIMEOUT_SECONDS = 15;

    public const int START_DELAY_SECONDS = 3;

    public const int SESSION_TTL_SECONDS = 1800;

    private int $widenStepSeconds = 10;

    private int $widenPerStep = 25;

    public function __construct(
        private readonly RedisManager $redis,
        private readonly GroupAssembler $groupAssembler,
    ) {}

    public function validateSession(int $userId, string $sessionId): void
    {
        $activeSession = $this->getActiveSession($userId);
        if ($activeSession !== null && $activeSession !== $sessionId) {
            throw new ConflictHttpException('MULTI_TAB: Another session is active');
        }
    }

    public function setActiveSession(int $userId, string $sessionId): void
    {
        $key = self::ACTIVE_SESSION_PREFIX.$userId;
        $this->redis->setex($key, self::SESSION_TTL_SECONDS, $sessionId);
    }

    public function clearActiveSession(int $userId): void
    {
        $key = self::ACTIVE_SESSION_PREFIX.$userId;
        $this->redis->del($key);
    }

    public function startSearch(User $user, GameMode $mode, string $sessionId): array
    {
        $this->validateSession($user->id, $sessionId);

        $groupKey = "u:{$user->id}";
        $hashKey = self::GROUP_KEY_PREFIX.$groupKey;
        $queueKey = self::QUEUE_KEYS[$mode->value];

        $now = now()->timestamp;
        $userMmr = $user->mmr ?? 0;

        $members = [['user_id' => $user->id, 'mmr' => $userMmr]];

        $this->redis->hmset($hashKey, [
            'group_key' => $groupKey,
            'mode' => $mode->value,
            'mmr' => $userMmr,
            'size' => 1,
            'status' => GroupStatus::Searching->value,
            'search_started_at' => $now,
            'search_expires_at' => $now + self::SEARCH_TIMEOUT_SECONDS,
            'ticket_id' => '',
            'session_id' => $sessionId,
            'members_json' => json_encode($members),
            'updated_at' => $now,
        ]);
        $this->redis->expire($hashKey, self::SEARCH_TIMEOUT_SECONDS + 60);

        $this->redis->zadd($queueKey, [$groupKey => $userMmr]);

        $this->setActiveSession($user->id, $sessionId);

        broadcast(new MMSearchUpdated(
            userHash: $user->getHashedId(),
            state: GroupStatus::Searching,
            mode: $mode->value,
            searchStartedAt: $now,
            searchExpiresAt: $now + self::SEARCH_TIMEOUT_SECONDS,
        ));

        return [
            'state' => GroupStatus::Searching->value,
            'mode' => $mode->value,
            'searchStartedAt' => $now,
            'searchExpiresAt' => $now + self::SEARCH_TIMEOUT_SECONDS,
            'sessionId' => $sessionId,
        ];
    }

    public function cancelSearch(User $user, string $sessionId): void
    {
        $this->validateSession($user->id, $sessionId);

        $groupKey = "u:{$user->id}";
        $hashKey = self::GROUP_KEY_PREFIX.$groupKey;

        $hash = $this->redis->hgetall($hashKey);
        if (empty($hash)) {
            return;
        }

        $status = $hash['status'] ?? '';
        if ($status === GroupStatus::Searching->value) {
            $mode = $hash['mode'];
            $queueKey = self::QUEUE_KEYS[$mode];
            $this->redis->zrem($queueKey, $groupKey);
        }

        if ($status === GroupStatus::Proposed->value && !empty($hash['ticket_id'])) {
            $this->declineTicket($user, $hash['ticket_id'], $sessionId);

            return;
        }

        $this->redis->del($hashKey);
        $this->clearActiveSession($user->id);

        broadcast(new MMSearchUpdated(
            userHash: $user->getHashedId(),
            state: GroupStatus::Idle,
            reason: CancelReason::UserCancelled->value,
        ));
    }

    public function getState(User $user): array
    {
        $groupKey = "u:{$user->id}";
        $hashKey = self::GROUP_KEY_PREFIX.$groupKey;

        $hash = $this->redis->hgetall($hashKey);
        if (empty($hash)) {
            return ['state' => GroupStatus::Idle->value];
        }

        $status = $hash['status'] ?? GroupStatus::Idle->value;

        $result = [
            'state' => $status,
            'mode' => $hash['mode'] ?? null,
        ];

        if ($status === GroupStatus::Searching->value) {
            $result['searchStartedAt'] = (int) ($hash['search_started_at'] ?? 0);
            $result['searchExpiresAt'] = (int) ($hash['search_expires_at'] ?? 0);
        }

        if (in_array($status, [GroupStatus::Proposed->value, GroupStatus::Starting->value], true)) {
            $ticketId = $hash['ticket_id'] ?? '';
            if ($ticketId) {
                $ticketKey = self::TICKET_KEY_PREFIX.$ticketId;
                $ticket = $this->redis->hgetall($ticketKey);
                if ($ticket) {
                    $result['ticketId'] = $ticketId;
                    $result['readyExpiresAt'] = (int) ($ticket['ready_expires_at'] ?? 0);
                    $slots = json_decode($ticket['slots_json'] ?? '[]', true);
                    $result['slots'] = $slots;

                    // Find the user's slot
                    foreach ($slots as $slot) {
                        if ($slot['user_id'] === $user->id) {
                            $result['yourSlot'] = $slot['slot'];
                            break;
                        }
                    }

                    if ($status === GroupStatus::Starting->value) {
                        $result['startAt'] = (int) ($ticket['start_at'] ?? 0);
                    }
                }
            }
        }

        if ($status === GroupStatus::InMatch->value) {
            $result['matchId'] = (int) ($hash['match_id'] ?? 0);
        }

        return $result;
    }

    public function acceptTicket(User $user, string $ticketId, string $sessionId): void
    {
        $this->validateSession($user->id, $sessionId);

        $ticketKey = self::TICKET_KEY_PREFIX.$ticketId;
        $ticket = $this->redis->hgetall($ticketKey);

        if (empty($ticket) || $ticket['status'] !== TicketStatus::Pending->value) {
            return;
        }

        $now = now()->timestamp;
        if ($now > (int) ($ticket['ready_expires_at'] ?? 0)) {
            return;
        }

        $slots = json_decode($ticket['slots_json'] ?? '[]', true);
        $userSlot = null;
        foreach ($slots as $i => $slot) {
            if ($slot['user_id'] === $user->id) {
                $slots[$i]['status'] = SlotStatus::Accepted->value;
                $userSlot = $slot['slot'];
                break;
            }
        }

        if ($userSlot === null) {
            return;
        }

        $this->redis->sadd("{$ticketKey}:accepted", $user->id);
        $this->redis->hset($ticketKey, 'slots_json', json_encode($slots));

        $acceptedCount = (int) $this->redis->scard("{$ticketKey}:accepted");
        $declinedCount = (int) $this->redis->scard("{$ticketKey}:declined");
        $slotsTotal = (int) ($ticket['slots_total'] ?? count($slots));

        $this->broadcastTicketUpdate($ticketId, $slots, [
            ['slot' => $userSlot, 'status' => SlotStatus::Accepted->value],
        ], $acceptedCount, $declinedCount);

        if ($acceptedCount === $slotsTotal) {
            $this->confirmTicket($ticketId);
        }
    }

    public function declineTicket(User $user, string $ticketId, string $sessionId): void
    {
        $this->validateSession($user->id, $sessionId);

        $ticketKey = self::TICKET_KEY_PREFIX.$ticketId;
        $ticket = $this->redis->hgetall($ticketKey);

        if (empty($ticket) || $ticket['status'] !== TicketStatus::Pending->value) {
            return;
        }

        $this->redis->sadd("{$ticketKey}:declined", $user->id);
        $this->redis->hset($ticketKey, 'status', TicketStatus::Cancelled->value);

        $this->resolveTicketDecline($ticketId, $user->id, CancelReason::Declined);
    }

    public function expireTicket(string $ticketId): void
    {
        $ticketKey = self::TICKET_KEY_PREFIX.$ticketId;
        $ticket = $this->redis->hgetall($ticketKey);

        if (empty($ticket) || $ticket['status'] !== TicketStatus::Pending->value) {
            return;
        }

        $this->redis->hset($ticketKey, 'status', TicketStatus::Expired->value);

        $slots = json_decode($ticket['slots_json'] ?? '[]', true);
        $acceptedUsers = $this->redis->smembers("{$ticketKey}:accepted") ?? [];

        $timeoutUserId = null;
        foreach ($slots as $slot) {
            if (!in_array($slot['user_id'], $acceptedUsers, false)) {
                $timeoutUserId = $slot['user_id'];
                break;
            }
        }

        if ($timeoutUserId) {
            $this->resolveTicketDecline($ticketId, (int) $timeoutUserId, CancelReason::Timeout);
        }
    }

    public function startMatch(string $ticketId): void
    {
        $ticketKey = self::TICKET_KEY_PREFIX.$ticketId;
        $ticket = $this->redis->hgetall($ticketKey);

        if (empty($ticket) || $ticket['status'] !== TicketStatus::Confirmed->value) {
            return;
        }

        $mode = GameMode::from($ticket['mode']);
        $slots = json_decode($ticket['slots_json'] ?? '[]', true);
        $groupKeys = json_decode($ticket['group_keys_json'] ?? '[]', true);

        $players = array_column($slots, 'user_id');
        $teams = $this->buildTeamsFromSlots($slots, $mode);

        $match = new GameMatch;
        $match->mode = $mode->value;
        $match->status = 'active';
        $match->players = $players;
        $match->teams = $teams;
        $match->save();

        foreach ($groupKeys as $gk) {
            $hashKey = self::GROUP_KEY_PREFIX.$gk;
            $this->redis->hmset($hashKey, [
                'status' => GroupStatus::InMatch->value,
                'match_id' => $match->id,
                'updated_at' => now()->timestamp,
            ]);
        }

        foreach ($slots as $slot) {
            $user = User::find($slot['user_id']);
            if ($user) {
                broadcast(new MMMatchStarted(
                    userHash: $user->getHashedId(),
                    matchId: $match->id,
                ));
            }
        }
    }

    public function processTick(): void
    {
        foreach (GameMode::cases() as $mode) {
            $this->processMode($mode);
        }
    }

    private function getActiveSession(int $userId): ?string
    {
        $key = self::ACTIVE_SESSION_PREFIX.$userId;

        return $this->redis->get($key);
    }

    private function confirmTicket(string $ticketId): void
    {
        $ticketKey = self::TICKET_KEY_PREFIX.$ticketId;

        $now = now()->timestamp;
        $startAt = $now + self::START_DELAY_SECONDS;

        $this->redis->hmset($ticketKey, [
            'status' => TicketStatus::Confirmed->value,
            'start_at' => $startAt,
        ]);

        $ticket = $this->redis->hgetall($ticketKey);
        $slots = json_decode($ticket['slots_json'] ?? '[]', true);
        $groupKeys = json_decode($ticket['group_keys_json'] ?? '[]', true);

        foreach ($groupKeys as $gk) {
            $hashKey = self::GROUP_KEY_PREFIX.$gk;
            $this->redis->hset($hashKey, 'status', GroupStatus::Starting->value);
        }

        foreach ($slots as $slot) {
            $user = User::find($slot['user_id']);
            if ($user) {
                broadcast(new MMStarting(
                    userHash: $user->getHashedId(),
                    ticketId: $ticketId,
                    startAt: $startAt,
                ));
            }
        }

        dispatch(new MatchStartJob($ticketId))->delay(now()->addSeconds(self::START_DELAY_SECONDS));
    }

    private function broadcastTicketUpdate(string $ticketId, array $slots, array $updates, int $acceptedCount, int $declinedCount): void
    {
        foreach ($slots as $slot) {
            $user = User::find($slot['user_id']);
            if ($user) {
                broadcast(new MMTicketUpdated(
                    userHash: $user->getHashedId(),
                    ticketId: $ticketId,
                    updates: $updates,
                    acceptedCount: $acceptedCount,
                    declinedCount: $declinedCount,
                ));
            }
        }
    }

    private function resolveTicketDecline(string $ticketId, int $declinedUserId, CancelReason $reason): void
    {
        $ticketKey = self::TICKET_KEY_PREFIX.$ticketId;
        $ticket = $this->redis->hgetall($ticketKey);

        if (empty($ticket)) {
            return;
        }

        $mode = GameMode::from($ticket['mode']);
        $slots = json_decode($ticket['slots_json'] ?? '[]', true);
        $groupKeys = json_decode($ticket['group_keys_json'] ?? '[]', true);

        $declinedGroupKey = null;
        foreach ($slots as $slot) {
            if ($slot['user_id'] === $declinedUserId) {
                $declinedGroupKey = $slot['group_key'];
                break;
            }
        }

        $stoppedGroupKeys = [];
        $returnGroupKeys = [];

        if ($declinedGroupKey !== null) {
            if (str_starts_with($declinedGroupKey, 'u:')) {
                $stoppedGroupKeys[] = $declinedGroupKey;
            } else {
                $stoppedGroupKeys[] = $declinedGroupKey;
            }
        }

        foreach ($groupKeys as $gk) {
            if (!in_array($gk, $stoppedGroupKeys, true)) {
                $returnGroupKeys[] = $gk;
            }
        }

        foreach ($stoppedGroupKeys as $gk) {
            $hashKey = self::GROUP_KEY_PREFIX.$gk;
            $this->redis->del($hashKey);

            $userId = $this->extractUserIdFromGroupKey($gk);
            if ($userId) {
                $this->clearActiveSession($userId);
                $user = User::find($userId);
                if ($user) {
                    broadcast(new MMTicketExpired(
                        userHash: $user->getHashedId(),
                        ticketId: $ticketId,
                        reason: $reason->value,
                        backToSearch: false,
                    ));
                }
            }
        }

        $now = now()->timestamp;
        $queueKey = self::QUEUE_KEYS[$mode->value];

        foreach ($returnGroupKeys as $gk) {
            $hashKey = self::GROUP_KEY_PREFIX.$gk;
            $hash = $this->redis->hgetall($hashKey);

            if (empty($hash)) {
                continue;
            }

            $mmr = (int) ($hash['mmr'] ?? 0);

            $this->redis->hmset($hashKey, [
                'status' => GroupStatus::Searching->value,
                'search_started_at' => $now,
                'search_expires_at' => $now + self::SEARCH_TIMEOUT_SECONDS,
                'ticket_id' => '',
                'updated_at' => $now,
            ]);

            $this->redis->zadd($queueKey, [$gk => $mmr]);

            $members = json_decode($hash['members_json'] ?? '[]', true);
            foreach ($members as $member) {
                $user = User::find($member['user_id']);
                if ($user) {
                    broadcast(new MMTicketExpired(
                        userHash: $user->getHashedId(),
                        ticketId: $ticketId,
                        reason: $reason->value,
                        backToSearch: true,
                    ));

                    broadcast(new MMSearchUpdated(
                        userHash: $user->getHashedId(),
                        state: GroupStatus::Searching,
                        mode: $mode->value,
                        searchStartedAt: $now,
                        searchExpiresAt: $now + self::SEARCH_TIMEOUT_SECONDS,
                    ));
                }
            }
        }
    }

    private function extractUserIdFromGroupKey(string $groupKey): ?int
    {
        if (str_starts_with($groupKey, 'u:')) {
            return (int) substr($groupKey, 2);
        }

        return null;
    }

    private function buildTeamsFromSlots(array $slots, GameMode $mode): array
    {
        if ($mode === GameMode::FreeForAll4) {
            return [array_column($slots, 'user_id')];
        }

        $teams = [];
        foreach ($slots as $slot) {
            $teamId = $slot['team_id'];
            if (!isset($teams[$teamId])) {
                $teams[$teamId] = [];
            }
            $teams[$teamId][] = $slot['user_id'];
        }

        return array_values($teams);
    }

    private function processMode(GameMode $mode): void
    {
        $queueKey = self::QUEUE_KEYS[$mode->value];

        $candidates = $this->redis->zrange($queueKey, 0, 99, true);

        if (empty($candidates)) {
            return;
        }

        $groupSnapshots = [];
        $now = now()->timestamp;

        foreach ($candidates as $groupKey => $score) {
            $hashKey = self::GROUP_KEY_PREFIX.$groupKey;
            $hash = $this->redis->hgetall($hashKey);

            if (empty($hash) || ($hash['status'] ?? '') !== GroupStatus::Searching->value) {
                $this->redis->zrem($queueKey, $groupKey);

                continue;
            }

            $expiresAt = (int) ($hash['search_expires_at'] ?? 0);
            if ($now > $expiresAt) {
                $this->expireSearch($groupKey, $hash);
                $this->redis->zrem($queueKey, $groupKey);

                continue;
            }

            $enq = (int) ($hash['search_started_at'] ?? $now);
            $elapsed = max(0, $now - $enq);
            $widenSteps = intdiv($elapsed, $this->widenStepSeconds);

            $base = (int) ($hash['mmr'] ?? 0);
            $hash['group_key'] = $groupKey;
            $hash['effective_min'] = $base - $this->widenPerStep - ($widenSteps * $this->widenPerStep);
            $hash['effective_max'] = $base + $this->widenPerStep + ($widenSteps * $this->widenPerStep);
            $hash['base_mmr'] = $base;
            $hash['members'] = json_decode($hash['members_json'] ?? '[]', true);
            $hash['enqueued_at'] = $enq;

            $groupSnapshots[$groupKey] = $hash;
        }

        if (empty($groupSnapshots)) {
            return;
        }

        $match = $this->groupAssembler->assemble($mode, $groupSnapshots);
        if (!$match) {
            return;
        }

        $this->createTicket($mode, $match, $queueKey);
    }

    private function expireSearch(string $groupKey, array $hash): void
    {
        $hashKey = self::GROUP_KEY_PREFIX.$groupKey;
        $this->redis->del($hashKey);

        $members = json_decode($hash['members_json'] ?? '[]', true);
        foreach ($members as $member) {
            $this->clearActiveSession($member['user_id']);

            $user = User::find($member['user_id']);
            if ($user) {
                broadcast(new MMSearchUpdated(
                    userHash: $user->getHashedId(),
                    state: GroupStatus::Idle,
                    reason: CancelReason::SearchTimeout->value,
                ));
            }
        }
    }

    private function createTicket(GameMode $mode, array $match, string $queueKey): void
    {
        $ticketId = Str::uuid()->toString();
        $ticketKey = self::TICKET_KEY_PREFIX.$ticketId;
        $now = now()->timestamp;
        $readyExpiresAt = $now + self::READY_TIMEOUT_SECONDS;

        $slots = $this->buildSlotsFromMatch($match, $mode);
        $groupKeys = $match['group_ids'];

        $this->redis->hmset($ticketKey, [
            'ticket_id' => $ticketId,
            'mode' => $mode->value,
            'status' => TicketStatus::Pending->value,
            'created_at' => $now,
            'ready_expires_at' => $readyExpiresAt,
            'start_at' => 0,
            'slots_total' => count($slots),
            'slots_json' => json_encode($slots),
            'group_keys_json' => json_encode($groupKeys),
        ]);

        $this->redis->del("{$ticketKey}:accepted", "{$ticketKey}:declined");

        foreach ($groupKeys as $gk) {
            $this->redis->zrem($queueKey, $gk);

            $hashKey = self::GROUP_KEY_PREFIX.$gk;
            $this->redis->hmset($hashKey, [
                'status' => GroupStatus::Proposed->value,
                'ticket_id' => $ticketId,
                'updated_at' => $now,
            ]);
        }

        foreach ($slots as $slot) {
            $user = User::find($slot['user_id']);
            if ($user) {
                broadcast(new MMSearchUpdated(
                    userHash: $user->getHashedId(),
                    state: GroupStatus::Proposed,
                    mode: $mode->value,
                ));

                $slotsForBroadcast = array_map(fn ($s) => [
                    'slot' => $s['slot'],
                    'status' => $s['status'],
                ], $slots);

                broadcast(new MMTicketCreated(
                    userHash: $user->getHashedId(),
                    ticketId: $ticketId,
                    mode: $mode->value,
                    readyExpiresAt: $readyExpiresAt,
                    slotsTotal: count($slots),
                    slots: $slotsForBroadcast,
                    yourSlot: $slot['slot'],
                ));
            }
        }

        dispatch(new TicketExpiryJob($ticketId))->delay(now()->addSeconds(self::READY_TIMEOUT_SECONDS));
    }

    private function buildSlotsFromMatch(array $match, GameMode $mode): array
    {
        $slots = [];
        $slotNum = 1;

        $teams = $match['teams'];
        $groupIds = $match['group_ids'];

        $groupKeyByUserId = [];
        foreach ($groupIds as $gk) {
            $hashKey = self::GROUP_KEY_PREFIX.$gk;
            $hash = $this->redis->hgetall($hashKey);
            $members = json_decode($hash['members_json'] ?? '[]', true);
            foreach ($members as $m) {
                $groupKeyByUserId[$m['user_id']] = $gk;
            }
        }

        if ($mode === GameMode::FreeForAll4) {
            foreach ($teams[0] as $userId) {
                $slots[] = [
                    'slot' => $slotNum,
                    'group_key' => $groupKeyByUserId[$userId] ?? '',
                    'user_id' => $userId,
                    'team_id' => (string) $slotNum,
                    'status' => SlotStatus::Pending->value,
                ];
                $slotNum++;
            }
        } else {
            $teamLabels = ['A', 'B'];
            foreach ($teams as $teamIndex => $teamPlayers) {
                $teamId = $teamLabels[$teamIndex] ?? (string) ($teamIndex + 1);
                foreach ($teamPlayers as $userId) {
                    $slots[] = [
                        'slot' => $slotNum,
                        'group_key' => $groupKeyByUserId[$userId] ?? '',
                        'user_id' => $userId,
                        'team_id' => $teamId,
                        'status' => SlotStatus::Pending->value,
                    ];
                    $slotNum++;
                }
            }
        }

        return $slots;
    }
}
