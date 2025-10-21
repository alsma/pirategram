<?php

declare(strict_types=1);

namespace App\MatchMaking;

use App\MatchMaking\Broadcasting\PartyUpdated;
use App\MatchMaking\Models\Party;
use App\MatchMaking\Models\PartyMember;
use App\MatchMaking\ValueObjects\SearchStatus;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class PartyManager
{
    public const array MODES = ['1v1' => 2, '2v2' => 4, 'ffa4' => 4]; // mode => max players

    public function __construct(
        private readonly MatchMakingManager $matchMakingManager,
    ) {}

    /** Create party with leader as first member */
    public function createParty(int $leaderId): Party
    {
        return transaction(function () use ($leaderId) {
            PartyMember::where('user_id', $leaderId)->delete();

            $party = new Party;
            $party->leader()->associate($leaderId);
            $party->mode = '1v1';
            $party->status = 'idle';
            $party->save();

            $partyMember = new PartyMember;
            $partyMember->party()->associate($party);
            $partyMember->user()->associate($leaderId);
            $partyMember->save();

            $this->syncRedisParty($party->id);

            broadcast(new PartyUpdated($party->id, 'created', $this->partyPayload($party->id)));

            return $party;
        });
    }

    /** Destroy party; cancels matchmaking if active */
    public function disband(int $leaderId, int $partyId): void
    {
        $party = Party::lockForUpdate()->findOrFail($partyId);
        $this->assertLeader($party, $leaderId);

        transaction(function () use ($party) {
            $this->cancelIfSearching($party->id, $party->mode);
            PartyMember::where('party_id', $party->id)->delete();
            $party->delete();

            // Cleanup Redis
            Redis::del($this->rkParty($party->id));
            Redis::zrem(MatchMakingManager::QUEUE_KEYS[$party->mode], "party:{$party->id}");

            broadcast(new PartyUpdated($party->id, 'disbanded'));
        });
    }

    /** Generate a short-lived invite code */
    public function createInvite(int $leaderId, int $partyId, int $ttlSeconds = 300): string
    {
        $party = Party::findOrFail($partyId);
        $this->assertLeader($party, $leaderId);

        $code = strtoupper(Str::random(6));
        $key = "mm:invite:{$code}";

        Redis::setex($key, $ttlSeconds, json_encode(['party_id' => $partyId, 'mode' => $party->mode]));

        return $code;
    }

    /** Join by invite code */
    public function joinByCode(int $userId, string $code): void
    {
        $payload = Redis::get('mm:invite:'.strtoupper($code));
        if (!$payload) {
            throw new \DomainException('Invite code is invalid or expired.');
        }
        $payload = json_decode($payload, true);
        $this->join($userId, (int) $payload['party_id']);
    }

    /** Join by party id (e.g., friends list click) */
    public function join(int $userId, int $partyId): void
    {
        $lock = $this->lockParty($partyId);
        try {
            transaction(function () use ($userId, $partyId) {
                $party = Party::lockForUpdate()->findOrFail($partyId);

                $this->ensureCapacity($party);

                // A user can be in only one party
                PartyMember::where('user_id', $userId)->delete();

                PartyMember::firstOrCreate([
                    'party_id' => $party->id,
                    'user_id' => $userId,
                ]);

                $this->cancelIfSearching($party->id, $party->mode); // composition changed

                $this->syncRedisParty($party->id);
                broadcast(new PartyUpdated($party->id, 'member_joined', $this->partyPayload($party->id)));
            });
        } finally {
            $this->unlock($lock);
        }
    }

    /** Leave party; transfer leadership if needed, disband if last member */
    public function leave(int $userId, int $partyId): void
    {
        $lock = $this->lockParty($partyId);
        try {
            transaction(function () use ($userId, $partyId) {
                $party = Party::lockForUpdate()->findOrFail($partyId);

                PartyMember::where(['party_id' => $partyId, 'user_id' => $userId])->delete();

                $members = PartyMember::where('party_id', $partyId)->pluck('user_id')->all();
                if (empty($members)) {
                    // Disband
                    $this->cancelIfSearching($partyId, $party->mode);
                    $party->delete();
                    Redis::del($this->rkParty($partyId));
                    broadcast(new PartyUpdated($partyId, 'disbanded'));

                    return;
                }

                if ($party->leader_id === $userId) {
                    $party->leader_id = $members[0];
                    $party->save();
                }

                $this->cancelIfSearching($partyId, $party->mode); // composition changed

                $this->syncRedisParty($partyId);
                broadcast(new PartyUpdated($partyId, 'member_left', $this->partyPayload($partyId)));
            });
        } finally {
            $this->unlock($lock);
        }
    }

    /** Promote another member to leader */
    public function promote(int $leaderId, int $partyId, int $newLeaderUserId): void
    {
        $lock = $this->lockParty($partyId);
        try {
            transaction(function () use ($leaderId, $partyId, $newLeaderUserId) {
                $party = Party::lockForUpdate()->findOrFail($partyId);
                $this->assertLeader($party, $leaderId);

                $exists = PartyMember::where(['party_id' => $partyId, 'user_id' => $newLeaderUserId])->exists();
                if (!$exists) {
                    throw new \DomainException('User is not in the party.');
                }

                $party->leader_id = $newLeaderUserId;
                $party->save();

                $this->syncRedisParty($partyId);
                broadcast(new PartyUpdated($partyId, 'leader_changed', $this->partyPayload($partyId)));
            });
        } finally {
            $this->unlock($lock);
        }
    }

    /** Kick member (leader only) */
    public function kick(int $leaderId, int $partyId, int $memberUserId): void
    {
        $lock = $this->lockParty($partyId);
        try {
            transaction(function () use ($leaderId, $partyId, $memberUserId) {
                $party = Party::lockForUpdate()->findOrFail($partyId);
                $this->assertLeader($party, $leaderId);

                if ($memberUserId === $leaderId) {
                    throw new \DomainException('Leader cannot kick self.');
                }

                PartyMember::where(['party_id' => $partyId, 'user_id' => $memberUserId])->delete();

                $this->cancelIfSearching($partyId, $party->mode); // composition changed

                $this->syncRedisParty($partyId);
                broadcast(new PartyUpdated($partyId, 'member_kicked', $this->partyPayload($partyId)));
            });
        } finally {
            $this->unlock($lock);
        }
    }

    /** Change party mode (may reduce allowed size; validate before) */
    public function setMode(int $leaderId, int $partyId, string $mode): void
    {
        if (!array_key_exists($mode, self::MODES)) {
            throw new \InvalidArgumentException('Unsupported mode');
        }

        $lock = $this->lockParty($partyId);
        try {
            transaction(function () use ($leaderId, $partyId, $mode) {
                $party = Party::lockForUpdate()->findOrFail($partyId);
                $this->assertLeader($party, $leaderId);

                $count = PartyMember::where('party_id', $partyId)->count();
                $max = self::MODES[$mode];
                if ($count > $max) {
                    throw new \DomainException("Too many members for mode {$mode} (max {$max}).");
                }

                $party->mode = $mode;
                $party->save();

                $this->cancelIfSearching($partyId, $mode); // rules changed

                $this->syncRedisParty($partyId);
                broadcast(new PartyUpdated($partyId, 'mode_changed', $this->partyPayload($partyId)));
            });
        } finally {
            $this->unlock($lock);
        }
    }

    /** Helper: push current composition into Redis hash the matcher uses */
    public function syncRedisParty(int $partyId): void
    {
        $party = Party::findOrFail($partyId);
        $members = PartyMember::with('user.profile')  // assume profile has MMR
            ->where('party_id', $partyId)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->user_id,
                'mmr' => (int) ($m->user->profile->mmr ?? 1200),
            ])->values()->all();

        $avgMmr = (int) round(collect($members)->avg('mmr') ?? 1200);

        // Update Redis party hash (so MatchMakingManager.startSearch can reuse)
        Redis::hmset($this->rkParty($partyId), [
            'party_id' => $partyId,
            'leader_id' => $party->leader_id,
            'members_json' => json_encode($members),
            'size' => count($members),
            'base_mmr' => $avgMmr,
            'mode' => $party->mode,
            'status' => SearchStatus::Idle->value,
        ]);
    }

    /** Cancel active search if any (idempotent) */
    private function cancelIfSearching(int $partyId, string $mode): void
    {
        $hash = Redis::hgetall($this->rkParty($partyId));
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

    private function assertLeader(Party $party, int $userId): void
    {
        if ($party->leader_id !== $userId) {
            throw new \DomainException('Only the party leader can perform this action.');
        }
    }

    private function rkParty(int $partyId): string
    {
        return MatchMakingManager::PARTY_KEY_PREFIX.$partyId;
    }

    /** Simple Redis lock around a party to avoid racey joins/kicks/promotes */
    private function lockParty(int $partyId, int $ttlMs = 5000): array
    {
        $key = "mm:lock:party:{$partyId}";
        $val = Str::uuid()->toString();

        $acquired = Redis::set($key, $val, 'PX', $ttlMs, 'NX');
        if (!$acquired) {
            throw new \RuntimeException('Party is busy, please retry.');
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
            Redis::eval($lua, 1, $key, $val);
        } catch (\Throwable) {
            // swallow
        }
    }

    /** Payload for broadcasting current state to clients */
    private function partyPayload(int $partyId): array
    {
        $party = Party::findOrFail($partyId);
        $members = PartyMember::with('user:id,name')
            ->where('party_id', $partyId)
            ->get()
            ->map(fn ($m) => ['id' => $m->user_id, 'name' => $m->user->name])
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
