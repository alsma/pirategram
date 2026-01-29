<?php

declare(strict_types=1);

namespace App\MatchMaking;

use App\MatchMaking\Broadcasting\PartyUpdated;
use App\MatchMaking\Models\Party;
use App\MatchMaking\Models\PartyMember;
use App\MatchMaking\Support\MatchMakingRedisKeys;
use App\MatchMaking\ValueObjects\GameMode;
use App\MatchMaking\ValueObjects\GroupStatus;
use App\MatchMaking\ValueObjects\PartyAction;
use App\MatchMaking\ValueObjects\PartyStatus;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Str;

class PartyManager
{
    public const array MODES = [
        GameMode::TwoVsTwo->value => 4,
    ];

    public function __construct(
        private readonly MatchMakingManager $matchMakingManager,
        private readonly RedisManager $redis,
    ) {}

    public function createParty(int $leaderId): Party
    {
        return transaction(function () use ($leaderId) {
            PartyMember::where('user_id', $leaderId)->delete();

            $party = new Party;
            $party->leader()->associate($leaderId);
            $party->mode = GameMode::TwoVsTwo->value;
            $party->status = PartyStatus::Idle->value;
            $party->save();

            $partyMember = new PartyMember;
            $partyMember->party()->associate($party);
            $partyMember->user()->associate($leaderId);
            $partyMember->save();

            $this->syncRedisParty($party->id);

            broadcast(new PartyUpdated($party->id, PartyAction::Created->value, $this->partyPayload($party->id)));

            return $party;
        });
    }

    public function disband(int $leaderId, Party $party): void
    {
        $lock = $this->lockParty($party->id);
        try {
            transaction(function () use ($leaderId, $party) {
                $party = Party::lockForUpdate()->findOrFail($party->id);
                $this->assertLeader($party, $leaderId);

                $this->cancelIfSearching($party->id, $party->mode);
                PartyMember::where('party_id', $party->id)->delete();
                $party->delete();

                $this->redis->del($this->rkParty($party->id));
                $this->redis->zrem(MatchMakingRedisKeys::QUEUE_KEYS[$party->mode], "party:{$party->id}");

                broadcast(new PartyUpdated($party->id, PartyAction::Disbanded->value));
            });
        } finally {
            $this->unlock($lock);
        }
    }

    public function createInvite(int $leaderId, int $invitedUserId, string $mode, int $ttlSeconds = 120): void
    {
        // Use leader-level lock to prevent concurrent invite operations by same leader
        $leaderLock = $this->lockLeaderInvites($leaderId);
        try {
            $party = Party::where('leader_id', $leaderId)->first();
            if ($party) {
                $lock = $this->lockParty($party->id);
                try {
                    transaction(function () use ($party, $leaderId, $invitedUserId, &$mode, $ttlSeconds) {
                        $party = Party::lockForUpdate()->findOrFail($party->id);
                        $this->assertLeader($party, $leaderId);
                        $this->assertNotSearching($party);
                        $mode = $party->mode;
                        $this->redis->del($this->rkPartyInviteLeaderMode($leaderId));

                        $key = $this->rkPartyInvite($invitedUserId, $leaderId);
                        $payload = [
                            'leader_id' => $leaderId,
                            'mode' => $mode,
                            'party_id' => $party->id,
                        ];
                        $this->redis->setex($key, $ttlSeconds, json_encode($payload));
                        // Track this invite leader for easy cleanup
                        $this->redis->sadd($this->rkUserInviteLeaders($invitedUserId), $leaderId);
                        $this->redis->expire($this->rkUserInviteLeaders($invitedUserId), $ttlSeconds + 60);
                    });
                } finally {
                    $this->unlock($lock);
                }
            } else {
                // No party exists yet, use Redis to track mode consistency
                $existingMode = $this->redis->get($this->rkPartyInviteLeaderMode($leaderId));
                if ($existingMode && $existingMode !== $mode) {
                    throw new \DomainException('All invites must use the same mode.');
                }

                $this->redis->setex($this->rkPartyInviteLeaderMode($leaderId), $ttlSeconds, $mode);

                $key = $this->rkPartyInvite($invitedUserId, $leaderId);
                $payload = [
                    'leader_id' => $leaderId,
                    'mode' => $mode,
                ];
                $this->redis->setex($key, $ttlSeconds, json_encode($payload));
                // Track this invite leader for easy cleanup
                $this->redis->sadd($this->rkUserInviteLeaders($invitedUserId), $leaderId);
                $this->redis->expire($this->rkUserInviteLeaders($invitedUserId), $ttlSeconds + 60);
            }
        } finally {
            $this->unlock($leaderLock);
        }
    }

    public function acceptInvite(int $userId, int $leaderId): void
    {
        // Lock the user to prevent them from accepting multiple invites simultaneously
        $userLock = $this->lockUser($userId);
        try {
            // Check if user is already in a party before doing anything else
            if (PartyMember::where('user_id', $userId)->exists()) {
                throw new \DomainException('User already in a party.');
            }

            $payload = $this->redis->get($this->rkPartyInvite($userId, $leaderId));
            if (!$payload) {
                throw new \DomainException('Invite code is invalid or expired.');
            }

            $payload = json_decode($payload, true);

            // Lock leader to prevent concurrent party creation
            $leaderLock = $this->lockLeaderInvites($leaderId);
            try {
                $party = isset($payload['party_id'])
                    ? Party::findOrFail((int) $payload['party_id'])
                    : Party::where('leader_id', $leaderId)->first();

                if (!$party) {
                    $party = $this->createParty($leaderId);
                    if ($payload['mode'] !== $party->mode) {
                        $this->setMode($leaderId, $party, $payload['mode']);
                        $party->refresh();
                    }
                }

                $this->join($userId, $party);
                $this->clearUserInvites($userId);
                $this->redis->del($this->rkPartyInviteLeaderMode($leaderId));
            } finally {
                $this->unlock($leaderLock);
            }
        } finally {
            $this->unlock($userLock);
        }
    }

    public function declineInvite(int $userId, int $leaderId): void
    {
        $key = $this->rkPartyInvite($userId, $leaderId);
        $payload = $this->redis->get($key);

        if (!$payload) {
            throw new \DomainException('Invite code is invalid or expired.');
        }

        $this->redis->del($key);
        $this->redis->srem($this->rkUserInviteLeaders($userId), $leaderId);
    }

    public function join(int $userId, Party $party): void
    {
        $lock = $this->lockParty($party->id);
        try {
            transaction(function () use ($userId, $party) {
                $party = Party::lockForUpdate()->findOrFail($party->id);

                $existing = PartyMember::where('user_id', $userId)->first();
                if ($existing) {
                    if ($existing->party_id === $party->id) {
                        return;
                    }

                    throw new \DomainException('User already in a party.');
                }

                $this->ensureCapacity($party);

                PartyMember::firstOrCreate([
                    'party_id' => $party->id,
                    'user_id' => $userId,
                ]);

                $this->cancelIfSearching($party->id, $party->mode);

                $this->syncRedisParty($party->id);
                broadcast(new PartyUpdated($party->id, PartyAction::MemberJoined->value, $this->partyPayload($party->id)));
            });
        } finally {
            $this->unlock($lock);
        }
    }

    public function leave(int $userId, Party $party): void
    {
        $lock = $this->lockParty($party->id);
        try {
            transaction(function () use ($userId, $party) {
                $party = Party::lockForUpdate()->findOrFail($party->id);

                PartyMember::where(['party_id' => $party->id, 'user_id' => $userId])->delete();

                $members = PartyMember::where('party_id', $party->id)
                    ->orderBy('created_at')
                    ->pluck('user_id')
                    ->all();
                if (empty($members)) {
                    $this->cancelIfSearching($party->id, $party->mode);
                    $party->delete();
                    $this->redis->del($this->rkParty($party->id));
                    broadcast(new PartyUpdated($party->id, PartyAction::Disbanded->value));

                    return;
                }

                if ($party->leader_id === $userId) {
                    $party->leader_id = $members[0];
                    $party->save();
                }

                $this->cancelIfSearching($party->id, $party->mode);

                $this->syncRedisParty($party->id);
                broadcast(new PartyUpdated($party->id, PartyAction::MemberLeft->value, $this->partyPayload($party->id)));
            });
        } finally {
            $this->unlock($lock);
        }
    }

    public function promote(int $leaderId, Party $party, int $newLeaderUserId): void
    {
        $lock = $this->lockParty($party->id);
        try {
            transaction(function () use ($leaderId, $party, $newLeaderUserId) {
                $party = Party::lockForUpdate()->findOrFail($party->id);
                $this->assertLeader($party, $leaderId);

                $exists = PartyMember::where(['party_id' => $party->id, 'user_id' => $newLeaderUserId])->exists();
                if (!$exists) {
                    throw new \DomainException('User is not in the party.');
                }

                $party->leader_id = $newLeaderUserId;
                $party->save();

                $this->syncRedisParty($party->id);
                broadcast(new PartyUpdated($party->id, PartyAction::LeaderChanged->value, $this->partyPayload($party->id)));
            });
        } finally {
            $this->unlock($lock);
        }
    }

    public function kick(int $leaderId, Party $party, int $memberUserId): void
    {
        $lock = $this->lockParty($party->id);
        try {
            transaction(function () use ($leaderId, $party, $memberUserId) {
                $party = Party::lockForUpdate()->findOrFail($party->id);
                $this->assertLeader($party, $leaderId);

                if ($memberUserId === $leaderId) {
                    throw new \DomainException('Leader cannot kick self.');
                }

                PartyMember::where(['party_id' => $party->id, 'user_id' => $memberUserId])->delete();

                $this->cancelIfSearching($party->id, $party->mode);

                $this->syncRedisParty($party->id);
                broadcast(new PartyUpdated($party->id, PartyAction::MemberKicked->value, $this->partyPayload($party->id)));
            });
        } finally {
            $this->unlock($lock);
        }
    }

    public function setMode(int $leaderId, Party $party, string $mode): void
    {
        if (!array_key_exists($mode, self::MODES)) {
            throw new \InvalidArgumentException('Unsupported mode');
        }

        $lock = $this->lockParty($party->id);
        try {
            transaction(function () use ($leaderId, $party, $mode) {
                $party = Party::lockForUpdate()->findOrFail($party->id);
                $this->assertLeader($party, $leaderId);
                $this->assertNotSearching($party);

                $count = PartyMember::where('party_id', $party->id)->count();
                $max = self::MODES[$mode];
                if ($count > $max) {
                    throw new \DomainException("Too many members for mode {$mode} (max {$max}).");
                }

                $party->mode = $mode;
                $party->save();

                $this->cancelIfSearching($party->id, $mode);

                $this->syncRedisParty($party->id);
                broadcast(new PartyUpdated($party->id, PartyAction::ModeChanged->value, $this->partyPayload($party->id)));
            });
        } finally {
            $this->unlock($lock);
        }
    }

    public function syncRedisParty(int $partyId): void
    {
        $party = Party::findOrFail($partyId);
        $members = PartyMember::with('user')
            ->where('party_id', $partyId)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->user_id,
                'mmr' => (int) ($m->user->mmr ?? 0),
            ])->values()->all();

        $avgMmr = (int) round(collect($members)->avg('mmr') ?? 1200);

        $this->redis->hmset($this->rkParty($partyId), [
            'group_key' => "party:{$partyId}",
            'party_id' => $partyId,
            'leader_id' => $party->leader_id,
            'members_json' => json_encode($members),
            'size' => count($members),
            'mmr' => $avgMmr,
            'base_mmr' => $avgMmr,
            'mode' => $party->mode,
            'status' => GroupStatus::Idle->value,
        ]);
    }

    private function cancelIfSearching(int $partyId, string $mode): void
    {
        $hash = $this->redis->hgetall($this->rkParty($partyId));
        if (($hash['status'] ?? null) === 'searching') {
            $this->matchMakingManager->cancelSearch($partyId, $mode);
            // Broadcast cancel (MatchMakingManager already does)
        }
    }

    private function ensureCapacity(Party $party): void
    {
        $count = PartyMember::where('party_id', $party->id)->count();
        $max = self::MODES[$party->mode] ?? 4;
        if ($count >= $max) {
            throw new \DomainException("Party is full for mode {$party->mode}.");
        }
    }

    private function assertNotSearching(Party $party): void
    {
        $hash = $this->redis->hgetall($this->rkParty($party->id));
        if (($hash['status'] ?? null) === GroupStatus::Searching->value) {
            throw new \DomainException('Matchmaking is active for this party.');
        }
    }

    private function assertLeader(Party $party, int $userId): void
    {
        if ($party->leader_id !== $userId) {
            throw new \DomainException('Only the party leader can perform this action.');
        }
    }

    private function rkParty(int $partyId): string
    {
        return MatchMakingRedisKeys::GROUP_KEY_PREFIX."party:{$partyId}";
    }

    private function rkPartyInvite(int $userId, int $leaderId): string
    {
        return "mm:invite:user:{$userId}:leader:{$leaderId}";
    }

    private function rkPartyInviteLeaderMode(int $leaderId): string
    {
        return "mm:invite:leader:{$leaderId}:mode";
    }

    private function rkUserInviteLeaders(int $userId): string
    {
        return "mm:invite:user:{$userId}:leaders";
    }

    private function clearUserInvites(int $userId): void
    {
        // Get all leader IDs that have sent invites to this user
        $leaderIds = $this->redis->smembers($this->rkUserInviteLeaders($userId));

        if (!empty($leaderIds)) {
            foreach ($leaderIds as $leaderId) {
                $this->redis->del($this->rkPartyInvite($userId, (int) $leaderId));
            }
            $this->redis->del($this->rkUserInviteLeaders($userId));
        }
    }

    private function lockParty(int $partyId, int $ttlMs = 5000): array
    {
        $key = "mm:lock:party:{$partyId}";
        $val = Str::uuid()->toString();

        $acquired = $this->redis->set($key, $val, 'PX', $ttlMs, 'NX');
        if (!$acquired) {
            throw new \RuntimeException('Party is busy, please retry.');
        }

        return [$key, $val];
    }

    private function lockUser(int $userId, int $ttlMs = 5000): array
    {
        $key = "mm:lock:user:{$userId}";
        $val = Str::uuid()->toString();

        $acquired = $this->redis->set($key, $val, 'PX', $ttlMs, 'NX');
        if (!$acquired) {
            throw new \RuntimeException('User is busy, please retry.');
        }

        return [$key, $val];
    }

    private function lockLeaderInvites(int $leaderId, int $ttlMs = 5000): array
    {
        $key = "mm:lock:leader:invites:{$leaderId}";
        $val = Str::uuid()->toString();

        $acquired = $this->redis->set($key, $val, 'PX', $ttlMs, 'NX');
        if (!$acquired) {
            throw new \RuntimeException('Leader invite operation is busy, please retry.');
        }

        return [$key, $val];
    }

    private function unlock(array $lock): void
    {
        [$key, $val] = $lock;
        try {
            // release only if value matches (classic redis lock release)
            $lua = <<<'LUA'
if redis.call("GET", KEYS[1]) == ARGV[1] then
  return redis.call("DEL", KEYS[1])
else
  return 0
end
LUA;
            $this->redis->eval($lua, 1, $key, $val);
        } catch (\Throwable) {
            // swallow
        }
    }

    public function getUserParty(int $userId): ?Party
    {
        $member = PartyMember::where('user_id', $userId)->first();

        return $member ? Party::find($member->party_id) : null;
    }

    public function ensureIsLeader(Party $party, int $userId): void
    {
        if ($party->leader_id !== $userId) {
            throw new \DomainException('Only the party leader can perform this action.');
        }
    }

    private function partyPayload(int $partyId): array
    {
        $party = Party::findOrFail($partyId);
        $members = PartyMember::with('user:id,username')
            ->where('party_id', $partyId)
            ->get()
            ->map(fn ($m) => ['id' => $m->user_id, 'username' => $m->user->username])
            ->values()->all();

        return [
            'partyId' => $partyId,
            'leaderId' => $party->leader_id,
            'mode' => $party->mode,
            'members' => $members,
            'maxPlayers' => self::MODES[$party->mode] ?? 4,
        ];
    }
}
