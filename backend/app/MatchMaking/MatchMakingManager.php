<?php

declare(strict_types=1);

namespace App\MatchMaking;

use App\MatchMaking\Broadcasting\MMMatchStarted;
use App\MatchMaking\Broadcasting\MMSearchUpdated;
use App\MatchMaking\Broadcasting\MMStarting;
use App\MatchMaking\Broadcasting\MMTicketCreated;
use App\MatchMaking\Broadcasting\MMTicketExpired;
use App\MatchMaking\Broadcasting\MMTicketUpdated;
use App\MatchMaking\Data\MatchMakingIdleStateDTO;
use App\MatchMaking\Data\MatchMakingInMatchStateDTO;
use App\MatchMaking\Data\MatchMakingMatchStartedDTO;
use App\MatchMaking\Data\MatchMakingMatchStartingDTO;
use App\MatchMaking\Data\MatchMakingProposedStateDTO;
use App\MatchMaking\Data\MatchMakingSearchUpdateDTO;
use App\MatchMaking\Data\MatchMakingSearchingStateDTO;
use App\MatchMaking\Data\MatchMakingStartDTO;
use App\MatchMaking\Data\MatchMakingStartingStateDTO;
use App\MatchMaking\Data\MatchMakingState;
use App\MatchMaking\Data\MatchMakingTicketCreatedDTO;
use App\MatchMaking\Data\MatchMakingTicketExpiredDTO;
use App\MatchMaking\Data\MatchMakingTicketUpdatedDTO;
use App\MatchMaking\Jobs\MatchStartJob;
use App\MatchMaking\Jobs\TicketExpiryJob;
use App\MatchMaking\Models\GameMatch;
use App\MatchMaking\Support\MatchMakingRedisKeys;
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
    private const int SEARCH_TIMEOUT_SECONDS = 600;

    private const int READY_TIMEOUT_SECONDS = 15;

    private const int START_DELAY_SECONDS = 3;

    private const int SESSION_TTL_SECONDS = 1800;

    private int $widenStepSeconds = 10;

    private int $widenPerStep = 25;

    public function __construct(
        private readonly RedisManager $redis,
        private readonly GroupAssembler $groupAssembler,
    ) {}

    public function startSearch(User $user, GameMode $mode, string $sessionId): MatchMakingStartDTO
    {
        $this->validateSession($user->id, $sessionId);

        $groupKey = "u:{$user->id}";
        $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX.$groupKey;
        $queueKey = MatchMakingRedisKeys::QUEUE_KEYS[$mode->value];

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

        $searchUpdate = new MatchMakingSearchUpdateDTO(
            GroupStatus::Searching->value,
            $mode->value,
            $now,
            $now + self::SEARCH_TIMEOUT_SECONDS,
        );
        broadcast(new MMSearchUpdated($user->getHashedId(), $searchUpdate));

        return new MatchMakingStartDTO(
            GroupStatus::Searching->value,
            $mode->value,
            $now,
            $now + self::SEARCH_TIMEOUT_SECONDS,
            $sessionId,
        );
    }

    public function cancelSearch(User $user, string $sessionId): void
    {
        $this->validateSession($user->id, $sessionId);

        $groupKey = "u:{$user->id}";
        $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX.$groupKey;

        $hash = $this->redis->hgetall($hashKey);
        if (empty($hash)) {
            return;
        }

        $status = $hash['status'] ?? '';
        if ($status === GroupStatus::Searching->value) {
            $mode = $hash['mode'];
            $queueKey = MatchMakingRedisKeys::QUEUE_KEYS[$mode];
            $this->redis->zrem($queueKey, $groupKey);
        }

        if ($status === GroupStatus::Proposed->value && !empty($hash['ticket_id'])) {
            $this->cancelTicket($user->id, $hash['ticket_id'], CancelReason::UserCancelled);

            return;
        }

        $this->redis->del($hashKey);
        $this->clearActiveSession($user->id);

        $searchUpdate = new MatchMakingSearchUpdateDTO(
            GroupStatus::Idle->value,
            null,
            null,
            null,
            CancelReason::UserCancelled->value,
        );
        broadcast(new MMSearchUpdated($user->getHashedId(), $searchUpdate));
    }

    public function getState(User $user): MatchMakingState
    {
        $groupKey = "u:{$user->id}";
        $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX.$groupKey;

        $hash = $this->redis->hgetall($hashKey);
        if (empty($hash)) {
            return new MatchMakingIdleStateDTO(GroupStatus::Idle->value);
        }

        $storedSessionId = $hash['session_id'] ?? '';
        if ($storedSessionId !== '') {
            $this->setActiveSession($user->id, $storedSessionId);
        }

        $status = $hash['status'] ?? GroupStatus::Idle->value;
        $mode = (string) ($hash['mode'] ?? '');

        if ($status === GroupStatus::Searching->value) {
            return new MatchMakingSearchingStateDTO(
                $status,
                $mode,
                (int) ($hash['search_started_at'] ?? 0),
                (int) ($hash['search_expires_at'] ?? 0),
            );
        }

        if (in_array($status, [GroupStatus::Proposed->value, GroupStatus::Starting->value], true)) {
            $ticketId = (string) ($hash['ticket_id'] ?? '');
            if ($ticketId === '') {
                $this->redis->del($hashKey);
                $this->clearActiveSession($user->id);

                return new MatchMakingIdleStateDTO(GroupStatus::Idle->value);
            }

            $ticketKey = MatchMakingRedisKeys::TICKET_KEY_PREFIX.$ticketId;
            $ticket = $this->redis->hgetall($ticketKey);
            if (!$ticket) {
                $this->redis->del($hashKey);
                $this->clearActiveSession($user->id);

                return new MatchMakingIdleStateDTO(GroupStatus::Idle->value);
            }

            $readyExpiresAt = (int) ($ticket['ready_expires_at'] ?? 0);
            $slots = json_decode($ticket['slots_json'] ?? '[]', true);
            $yourSlot = (int) (collect($slots)->firstWhere('user_id', $user->id)['slot'] ?? 0);

            if ($status === GroupStatus::Starting->value) {
                return new MatchMakingStartingStateDTO(
                    $status,
                    $mode,
                    $ticketId,
                    $readyExpiresAt,
                    $slots,
                    $yourSlot,
                    (int) ($ticket['start_at'] ?? 0),
                );
            }

            return new MatchMakingProposedStateDTO(
                $status,
                $mode,
                $ticketId,
                $readyExpiresAt,
                $slots,
                $yourSlot,
            );
        }

        if ($status === GroupStatus::InMatch->value) {
            return new MatchMakingInMatchStateDTO($status, (int) ($hash['match_id'] ?? 0));
        }

        return new MatchMakingIdleStateDTO(GroupStatus::Idle->value);
    }

    public function acceptTicket(User $user, string $ticketId, string $sessionId): void
    {
        $this->validateSession($user->id, $sessionId);

        $ticketKey = MatchMakingRedisKeys::TICKET_KEY_PREFIX.$ticketId;
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
        $slots = collect($slots)
            ->map(function (array $slot) use ($user, &$userSlot) {
                if ($slot['user_id'] === $user->id) {
                    $slot['status'] = SlotStatus::Accepted->value;
                    $userSlot = $slot['slot'];
                }

                return $slot;
            })
            ->all();

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

        $this->cancelTicket($user->id, $ticketId, CancelReason::Declined);
    }

    public function expireTicket(string $ticketId): void
    {
        $ticketKey = MatchMakingRedisKeys::TICKET_KEY_PREFIX.$ticketId;
        if (!$this->acquireLock("{$ticketKey}:cancelling", 10)) {
            return;
        }

        $ticket = $this->redis->hgetall($ticketKey);

        if (empty($ticket) || $ticket['status'] !== TicketStatus::Pending->value) {
            return;
        }

        $this->redis->hset($ticketKey, 'status', TicketStatus::Expired->value);

        $slots = json_decode($ticket['slots_json'] ?? '[]', true);
        $acceptedUsers = collect($this->redis->smembers("{$ticketKey}:accepted") ?? [])
            ->map(static fn ($id) => (int) $id)
            ->all();

        $timeoutUserIds = collect($slots)
            ->map(fn (array $slot) => (int) ($slot['user_id'] ?? 0))
            ->filter(fn (int $userId) => $userId > 0 && !in_array($userId, $acceptedUsers, true))
            ->values()
            ->all();

        if ($timeoutUserIds !== []) {
            foreach ($timeoutUserIds as $userId) {
                $this->redis->sadd("{$ticketKey}:declined", $userId);
            }

            $this->resolveTicketDeclines($ticketId, $timeoutUserIds, CancelReason::Timeout);
        }
    }

    public function startMatch(string $ticketId): void
    {
        $ticketKey = MatchMakingRedisKeys::TICKET_KEY_PREFIX.$ticketId;
        $ticket = $this->redis->hgetall($ticketKey);

        if (empty($ticket) || $ticket['status'] !== TicketStatus::Confirmed->value) {
            return;
        }

        if (!$this->acquireLock("{$ticketKey}:starting", 30)) {
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

        $this->redis->hset($ticketKey, 'status', TicketStatus::Started->value);

        foreach ($groupKeys as $gk) {
            $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX.$gk;
            $this->redis->hmset($hashKey, [
                'status' => GroupStatus::InMatch->value,
                'match_id' => $match->id,
                'updated_at' => now()->timestamp,
            ]);
        }

        $usersById = $this->loadUsersByIds(array_column($slots, 'user_id'));
        foreach ($slots as $slot) {
            $user = $usersById[$slot['user_id']] ?? null;
            if ($user instanceof User) {
                $matchStarted = new MatchMakingMatchStartedDTO($match->id);
                broadcast(new MMMatchStarted($user->getHashedId(), $matchStarted));
            }
        }
    }

    public function processTick(): void
    {
        foreach (GameMode::cases() as $mode) {
            $this->processMode($mode);
        }
    }

    private function validateSession(int $userId, string $sessionId): void
    {
        $activeSession = $this->getActiveSession($userId);
        if ($activeSession !== null && $activeSession !== $sessionId) {
            throw new ConflictHttpException('MULTI_TAB: Another session is active');
        }
    }

    private function setActiveSession(int $userId, string $sessionId): void
    {
        $key = MatchMakingRedisKeys::ACTIVE_SESSION_PREFIX.$userId;
        $this->redis->setex($key, self::SESSION_TTL_SECONDS, $sessionId);
    }

    private function clearActiveSession(int $userId): void
    {
        $key = MatchMakingRedisKeys::ACTIVE_SESSION_PREFIX.$userId;
        $this->redis->del($key);
    }

    private function getActiveSession(int $userId): ?string
    {
        $key = MatchMakingRedisKeys::ACTIVE_SESSION_PREFIX.$userId;

        return $this->redis->get($key);
    }

    private function confirmTicket(string $ticketId): void
    {
        $ticketKey = MatchMakingRedisKeys::TICKET_KEY_PREFIX.$ticketId;

        if (!$this->acquireLock("{$ticketKey}:confirming", 30)) {
            return;
        }

        $ticket = $this->redis->hgetall($ticketKey);
        if (empty($ticket) || ($ticket['status'] ?? '') !== TicketStatus::Pending->value) {
            return;
        }

        $now = now()->timestamp;
        $startAt = $now + self::START_DELAY_SECONDS;

        $this->redis->hmset($ticketKey, [
            'status' => TicketStatus::Confirmed->value,
            'start_at' => $startAt,
        ]);

        $slots = json_decode($ticket['slots_json'] ?? '[]', true);
        $groupKeys = json_decode($ticket['group_keys_json'] ?? '[]', true);

        foreach ($groupKeys as $gk) {
            $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX.$gk;
            $this->redis->hset($hashKey, 'status', GroupStatus::Starting->value);
        }

        $usersById = $this->loadUsersByIds(array_column($slots, 'user_id'));
        foreach ($slots as $slot) {
            $user = $usersById[$slot['user_id']] ?? null;
            if ($user instanceof User) {
                $starting = new MatchMakingMatchStartingDTO($ticketId, $startAt);
                broadcast(new MMStarting($user->getHashedId(), $starting));
            }
        }

        dispatch(new MatchStartJob($ticketId))->delay(now()->addSeconds(self::START_DELAY_SECONDS));
    }

    private function broadcastTicketUpdate(string $ticketId, array $slots, array $updates, int $acceptedCount, int $declinedCount): void
    {
        $usersById = $this->loadUsersByIds(array_column($slots, 'user_id'));
        foreach ($slots as $slot) {
            $user = $usersById[$slot['user_id']] ?? null;
            if ($user instanceof User) {
                $ticketUpdate = new MatchMakingTicketUpdatedDTO(
                    $ticketId,
                    $updates,
                    $acceptedCount,
                    $declinedCount,
                );
                broadcast(new MMTicketUpdated($user->getHashedId(), $ticketUpdate));
            }
        }
    }

    /**
     * @param  array<int, int>  $declinedUserIds
     */
    private function resolveTicketDeclines(string $ticketId, array $declinedUserIds, CancelReason $reason): void
    {
        $ticketKey = MatchMakingRedisKeys::TICKET_KEY_PREFIX.$ticketId;
        $ticket = $this->redis->hgetall($ticketKey);

        if (empty($ticket)) {
            return;
        }

        $declinedUserIds = collect($declinedUserIds)
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($declinedUserIds === []) {
            return;
        }

        $mode = GameMode::from($ticket['mode']);
        $slots = json_decode($ticket['slots_json'] ?? '[]', true);
        $groupKeys = json_decode($ticket['group_keys_json'] ?? '[]', true);

        $declinedUserIdLookup = array_flip($declinedUserIds);
        $stoppedGroupKeys = collect($groupKeys)
            ->filter(function (string $gk) use ($declinedUserIdLookup): bool {
                $memberIds = $this->getMemberUserIdsForGroupKey($gk);
                foreach ($memberIds as $memberId) {
                    if (isset($declinedUserIdLookup[$memberId])) {
                        return true;
                    }
                }

                return false;
            })
            ->values()
            ->all();

        $returnGroupKeys = collect($groupKeys)
            ->reject(fn (string $gk) => in_array($gk, $stoppedGroupKeys, true))
            ->values()
            ->all();

        $now = now()->timestamp;
        $queueKey = MatchMakingRedisKeys::QUEUE_KEYS[$mode->value];

        $stoppedUserIds = collect($stoppedGroupKeys)
            ->flatMap(fn (string $gk) => $this->getMemberUserIdsForGroupKey($gk))
            ->unique()
            ->values()
            ->all();

        $returnGroupSnapshots = [];
        $returnGroupMembers = [];
        $memberUserIds = [];
        collect($returnGroupKeys)->each(function (string $gk) use ($now, $queueKey, &$returnGroupSnapshots, &$returnGroupMembers, &$memberUserIds): void {
            $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX.$gk;
            $hash = $this->redis->hgetall($hashKey);

            if (empty($hash)) {
                return;
            }

            $returnGroupSnapshots[$gk] = $hash;
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
            $memberIds = $this->extractMemberIds($members);
            $returnGroupMembers[$gk] = $memberIds;
            $memberUserIds = array_merge($memberUserIds, $memberIds);
        });

        $usersById = $this->loadUsersByIds(collect($stoppedUserIds)->merge($memberUserIds)->all());

        collect($stoppedGroupKeys)->each(function (string $gk) use ($ticketId, $reason, $usersById): void {
            $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX.$gk;
            $this->redis->del($hashKey);

            $memberIds = $this->getMemberUserIdsForGroupKey($gk);
            foreach ($memberIds as $userId) {
                $this->clearActiveSession($userId);
                $user = $usersById[$userId] ?? null;
                if ($user instanceof User) {
                    $ticketExpired = new MatchMakingTicketExpiredDTO(
                        $ticketId,
                        $reason->value,
                        false,
                    );
                    broadcast(new MMTicketExpired($user->getHashedId(), $ticketExpired));
                }
            }
        });

        collect($returnGroupSnapshots)->each(function (array $hash, string $gk) use ($returnGroupMembers, $usersById, $ticketId, $reason, $mode, $now): void {
            $memberIds = $returnGroupMembers[$gk] ?? [];
            foreach ($memberIds as $userId) {
                $user = $usersById[$userId] ?? null;
                if ($user instanceof User) {
                    $ticketExpired = new MatchMakingTicketExpiredDTO(
                        $ticketId,
                        $reason->value,
                        true,
                    );
                    broadcast(new MMTicketExpired($user->getHashedId(), $ticketExpired));

                    $searchUpdate = new MatchMakingSearchUpdateDTO(
                        GroupStatus::Searching->value,
                        $mode->value,
                        $now,
                        $now + self::SEARCH_TIMEOUT_SECONDS,
                    );
                    broadcast(new MMSearchUpdated($user->getHashedId(), $searchUpdate));
                }
            }
        });
    }

    private function extractUserIdFromGroupKey(string $groupKey): ?int
    {
        if (str_starts_with($groupKey, 'u:')) {
            return (int) substr($groupKey, 2);
        }

        return null;
    }

    /**
     * @return array<int, int>
     */
    private function getMemberUserIdsForGroupKey(string $groupKey): array
    {
        $userId = $this->extractUserIdFromGroupKey($groupKey);
        if ($userId) {
            return [$userId];
        }

        $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX.$groupKey;
        $hash = $this->redis->hgetall($hashKey);
        if (empty($hash)) {
            return [];
        }

        $members = json_decode($hash['members_json'] ?? '[]', true);

        return $this->extractMemberIds($members);
    }

    /**
     * @param  array<int, array<string, mixed>>  $members
     * @return array<int, int>
     */
    private function extractMemberIds(array $members): array
    {
        return collect($members)
            ->map(static fn (array $member) => (int) ($member['user_id'] ?? $member['id'] ?? 0))
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function buildTeamsFromSlots(array $slots, GameMode $mode): array
    {
        if ($mode === GameMode::FreeForAll4) {
            return [array_column($slots, 'user_id')];
        }

        return collect($slots)
            ->groupBy('team_id')
            ->map(fn ($teamSlots) => $teamSlots->pluck('user_id')->all())
            ->values()
            ->all();
    }

    private function processMode(GameMode $mode): void
    {
        $queueKey = MatchMakingRedisKeys::QUEUE_KEYS[$mode->value];

        $candidates = $this->redis->zrange($queueKey, 0, 99, true);

        if (empty($candidates)) {
            return;
        }

        $groupSnapshots = [];
        $now = now()->timestamp;

        foreach ($candidates as $groupKey => $score) {
            $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX.$groupKey;
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
        $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX.$groupKey;
        $this->redis->del($hashKey);

        $members = json_decode($hash['members_json'] ?? '[]', true);
        $memberIds = $this->extractMemberIds($members);
        $usersById = $this->loadUsersByIds($memberIds);
        collect($memberIds)->each(function (int $memberId) use ($usersById): void {
            $this->clearActiveSession($memberId);

            $user = $usersById[$memberId] ?? null;
            if ($user instanceof User) {
                $searchUpdate = new MatchMakingSearchUpdateDTO(
                    GroupStatus::Idle->value,
                    null,
                    null,
                    null,
                    CancelReason::SearchTimeout->value,
                );
                broadcast(new MMSearchUpdated($user->getHashedId(), $searchUpdate));
            }
        });
    }

    private function createTicket(GameMode $mode, array $match, string $queueKey): void
    {
        $ticketId = Str::uuid()->toString();
        $ticketKey = MatchMakingRedisKeys::TICKET_KEY_PREFIX.$ticketId;
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

            $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX.$gk;
            $this->redis->hmset($hashKey, [
                'status' => GroupStatus::Proposed->value,
                'ticket_id' => $ticketId,
                'updated_at' => $now,
            ]);
        }

        $usersById = $this->loadUsersByIds(array_column($slots, 'user_id'));
        foreach ($slots as $slot) {
            $user = $usersById[$slot['user_id']] ?? null;
            if ($user instanceof User) {
                $searchUpdate = new MatchMakingSearchUpdateDTO(
                    GroupStatus::Proposed->value,
                    $mode->value,
                );
                broadcast(new MMSearchUpdated($user->getHashedId(), $searchUpdate));

                $slotsForBroadcast = array_map(fn ($s) => [
                    'slot' => $s['slot'],
                    'status' => $s['status'],
                ], $slots);

                $ticketCreated = new MatchMakingTicketCreatedDTO(
                    $ticketId,
                    $mode->value,
                    $readyExpiresAt,
                    count($slots),
                    $slotsForBroadcast,
                    $slot['slot'],
                );
                broadcast(new MMTicketCreated($user->getHashedId(), $ticketCreated));
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

        $groupKeyByUserId = collect($groupIds)
            ->flatMap(function (string $gk): array {
                $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX.$gk;
                $hash = $this->redis->hgetall($hashKey);
                $members = json_decode($hash['members_json'] ?? '[]', true);

                return collect($members)
                    ->mapWithKeys(function (array $member) use ($gk): array {
                        $userId = (int) ($member['user_id'] ?? $member['id'] ?? 0);
                        if ($userId <= 0) {
                            return [];
                        }

                        return [$userId => $gk];
                    })
                    ->all();
            })
            ->all();

        if ($mode === GameMode::FreeForAll4) {
            $slots = collect($teams[0])
                ->map(function (int $userId) use (&$slotNum, $groupKeyByUserId): array {
                    $slot = [
                        'slot' => $slotNum,
                        'group_key' => $groupKeyByUserId[$userId] ?? '',
                        'user_id' => $userId,
                        'team_id' => (string) $slotNum,
                        'status' => SlotStatus::Pending->value,
                    ];
                    $slotNum++;

                    return $slot;
                })
                ->all();
        } else {
            $teamLabels = ['A', 'B'];
            $slots = collect($teams)
                ->flatMap(function (array $teamPlayers, int $teamIndex) use (&$slotNum, $teamLabels, $groupKeyByUserId): array {
                    $teamId = $teamLabels[$teamIndex] ?? (string) ($teamIndex + 1);

                    return collect($teamPlayers)
                        ->map(function (int $userId) use (&$slotNum, $teamId, $groupKeyByUserId): array {
                            $slot = [
                                'slot' => $slotNum,
                                'group_key' => $groupKeyByUserId[$userId] ?? '',
                                'user_id' => $userId,
                                'team_id' => $teamId,
                                'status' => SlotStatus::Pending->value,
                            ];
                            $slotNum++;

                            return $slot;
                        })
                        ->all();
                })
                ->all();
        }

        return $slots;
    }

    /**
     * @param  array<int, int|string>  $userIds
     * @return array<int, User>
     */
    private function loadUsersByIds(array $userIds): array
    {
        $ids = collect($userIds)
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return User::query()->whereIn('id', $ids)->get()->keyBy('id')->all();
    }

    private function cancelTicket(int $userId, string $ticketId, CancelReason $reason): void
    {
        $ticketKey = MatchMakingRedisKeys::TICKET_KEY_PREFIX.$ticketId;
        if (!$this->acquireLock("{$ticketKey}:cancelling", 10)) {
            return;
        }

        $ticket = $this->redis->hgetall($ticketKey);

        if (empty($ticket) || $ticket['status'] !== TicketStatus::Pending->value) {
            return;
        }

        $this->redis->sadd("{$ticketKey}:declined", $userId);
        $this->redis->hset($ticketKey, 'status', TicketStatus::Cancelled->value);

        $this->resolveTicketDeclines($ticketId, [$userId], $reason);
    }

    private function acquireLock(string $key, int $ttlSeconds): bool
    {
        $acquired = (bool) $this->redis->setnx($key, '1');
        if ($acquired) {
            $this->redis->expire($key, $ttlSeconds);
        }

        return $acquired;
    }
}
