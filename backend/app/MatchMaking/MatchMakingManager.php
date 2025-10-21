<?php

declare(strict_types=1);

namespace App\MatchMaking;

use App\MatchMaking\Broadcasting\SearchUpdated;
use App\MatchMaking\Broadcasting\TicketCreated;
use App\MatchMaking\Broadcasting\TicketUpdated;
use App\MatchMaking\Jobs\TicketTimeoutJob;
use App\MatchMaking\Models\GameMatch;
use App\MatchMaking\ValueObjects\SearchStatus;
use App\MatchMaking\ValueObjects\TicketStatus;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Str;

class MatchMakingManager
{
    public const array MODES = ['1v1', '2v2', 'ffa4'];

    public const array QUEUE_KEYS = [
        '1v1' => 'mm:queue:1v1',
        '2v2' => 'mm:queue:2v2',
        'ffa4' => 'mm:queue:ffa4',
    ];

    public const string PARTY_KEY_PREFIX = 'mm:party:';

    public const string TICKET_KEY_PREFIX = 'mm:ticket:';

    private int $widenStepSeconds = 10; // widening config

    private int $widenPerStep = 25; // MMR points per step

    private int $baseTicketTtl = 15; // seconds for ready check

    public function __construct(
        private readonly RedisManager $redisManager,
        private readonly GroupAssembler $groupAssembler,
    ) {}

    /**
     * Start/replace search for a party (leader triggers it).
     * $party: ['party_id'=>int, 'leader_id'=>int, 'members'=>[ ['id'=>, 'mmr'=>], ...], 'mode'=>string]
     */
    public function startSearch(array $party): void
    {
        $this->assertMode($party['mode']);

        $partyId = (int) $party['party_id'];
        $queueKey = self::QUEUE_KEYS[$party['mode']];

        $members = collect($party['members']);
        $avgMmr = (int) $members->avg('mmr');

        $partyKey = self::PARTY_KEY_PREFIX.$partyId;

        $status = SearchStatus::Searching;

        // Store party state
        $size = $members->count();
        $this->redisManager->hmset($partyKey, [
            'party_id' => $partyId,
            'leader_id' => $party['leader_id'],
            'members_json' => json_encode($party['members']),
            'size' => $size,
            'base_mmr' => $avgMmr,
            'mode' => $party['mode'],
            'enqueued_at' => now()->timestamp,
            'status' => $status->value,
        ]);
        $this->redisManager->zadd($queueKey, $avgMmr, "party:{$partyId}");

        broadcast(new SearchUpdated($partyId, $status, [
            'mode' => $party['mode'],
            'avg_mmr' => $avgMmr,
            'size' => $size,
        ]));
    }

    public function cancelSearch(int $partyId, string $mode): void
    {
        $this->assertMode($mode);
        $queueKey = self::QUEUE_KEYS[$mode];
        $this->redisManager->zrem($queueKey, "party:{$partyId}");
        $this->redisManager->hset(self::PARTY_KEY_PREFIX.$partyId, 'status', SearchStatus::Cancelled->value);

        broadcast(new SearchUpdated($partyId, SearchStatus::Cancelled));
    }

    public function processTick(): void
    {
        foreach (self::MODES as $mode) {
            $this->processMode($mode);
        }
    }

    private function processMode(string $mode): void
    {
        $queueKey = self::QUEUE_KEYS[$mode];

        // Pull top N entries to consider (bounded for perf; widen logic increases acceptance window)
        $candidateEntries = $this->redisManager->zrange($queueKey, 0, 99, true); // member => score
        if (empty($candidateEntries)) {
            return;
        }

        // Build party snapshots
        $partySnapshots = [];
        foreach ($candidateEntries as $member => $score) {
            $partyId = (int) str_replace('party:', '', $member);
            $partyKey = self::PARTY_KEY_PREFIX.$partyId;
            $hash = $this->redisManager->hgetall($partyKey);
            if (!$hash || ($hash['status'] ?? null) !== SearchStatus::Searching->value) {
                // Remove stale
                $this->redisManager->zrem($queueKey, $member);

                continue;
            }
            $enq = (int) $hash['enqueued_at'];
            $elapsed = max(0, now()->timestamp - $enq);
            $widenSteps = intdiv($elapsed, $this->widenStepSeconds);
            $hash['widen'] = $widenSteps;
            $hash['effective_min'] = (int) $hash['base_mmr'] - $this->widenPerStep - ($widenSteps * $this->widenPerStep);
            $hash['effective_max'] = (int) $hash['base_mmr'] + $this->widenPerStep + ($widenSteps * $this->widenPerStep);
            $hash['members'] = json_decode($hash['members_json'], true);
            $partySnapshots[$partyId] = $hash;
        }

        if (empty($partySnapshots)) {
            return;
        }

        // Try to assemble groups depending on mode
        $ticket = $this->groupAssembler->assemble($mode, $partySnapshots);
        if (!$ticket) {
            return;
        }

        // Remove all matched parties from queue and mark status=matched
        foreach ($ticket['party_ids'] as $pid) {
            $this->redisManager->zrem($queueKey, "party:{$pid}");
            $this->redisManager->hset(self::PARTY_KEY_PREFIX.$pid, 'status', SearchStatus::Matched->value);
        }

        // Create ticket
        $ticketId = Str::uuid()->toString();
        $expiresAt = now()->addSeconds($this->baseTicketTtl)->unix();
        $ticketKey = self::TICKET_KEY_PREFIX.$ticketId;

        $this->redisManager->hmset($ticketKey, [
            'ticket_id' => $ticketId,
            'mode' => $mode,
            'teams_json' => json_encode($ticket['teams']),     // teams = [[userIds], [userIds]] or [[]] for FFA
            'players_json' => json_encode($ticket['players']),   // flat array of userIds
            'party_ids_json' => json_encode($ticket['party_ids']),   // flat array of partyId
            'expires_at' => $expiresAt,
            'status' => TicketStatus::Pending->value,
        ]);

        // Empty sets to track responses
        $this->redisManager->del("{$ticketKey}:accepted", "{$ticketKey}:declined");

        broadcast(new TicketCreated($ticketId, $mode, $ticket['teams'], $expiresAt));
        dispatch(new TicketTimeoutJob($ticketId))->delay(now()->addSeconds($this->baseTicketTtl));
    }

    public function acceptTicket(int $userId, string $ticketId): void
    {
        $ticketKey = self::TICKET_KEY_PREFIX.$ticketId;
        $status = $this->redisManager->hget($ticketKey, 'status');
        if ($status !== TicketStatus::Pending->value) {
            return;
        }

        $players = json_decode($this->redisManager->hget($ticketKey, 'players_json') ?? '[]', true);
        if (!in_array($userId, $players, true)) {
            return;
        }

        $this->redisManager->sadd("{$ticketKey}:accepted", $userId);
        broadcast(new TicketUpdated($ticketId, TicketStatus::Accepted, $userId));

        $acceptedCount = $this->redisManager->scard("{$ticketKey}:accepted");
        if ($acceptedCount === count($players)) {
            // everyone ready -> finalize match
            $this->finalizeTicket($ticketId);
        }
    }

    public function declineTicket(int $userId, string $ticketId): void
    {
        $ticketKey = self::TICKET_KEY_PREFIX.$ticketId;
        $status = $this->redisManager->hget($ticketKey, 'status');
        if ($status !== TicketStatus::Pending->value) {
            return;
        }

        $newStatus = TicketStatus::Declined;
        $this->redisManager->sadd("{$ticketKey}:declined", $userId);
        $this->redisManager->hset($ticketKey, 'status', $newStatus->value);
        broadcast(new TicketUpdated($ticketId, $newStatus, $userId));

        $this->returnPartiesToQueue($ticketId, penalizeUserId: $userId);
    }

    public function timeoutTicket(string $ticketId): void
    {
        $ticketKey = self::TICKET_KEY_PREFIX.$ticketId;
        $status = $this->redisManager->hget($ticketKey, 'status');
        if ($status !== TicketStatus::Pending->value) {
            return;
        }

        $newStatus = TicketStatus::Timeout;
        $this->redisManager->hset($ticketKey, 'status', $newStatus->value);
        broadcast(new TicketUpdated($ticketId, $newStatus, null));

        $this->returnPartiesToQueue($ticketId);
    }

    private function finalizeTicket(string $ticketId): void
    {
        $ticketKey = self::TICKET_KEY_PREFIX.$ticketId;
        $newStatus = TicketStatus::Confirmed;
        $this->redisManager->hset($ticketKey, 'status', $newStatus->value);

        $mode = $this->redisManager->hget($ticketKey, 'mode');
        $teams = json_decode($this->redisManager->hget($ticketKey, 'teams_json') ?? '[]', true);
        $players = json_decode($this->redisManager->hget($ticketKey, 'players_json') ?? '[]', true);

        $match = new GameMatch;
        $match->mode = $mode;
        $match->status = 'ready';
        $match->players = $players;
        $match->teams = $teams;
        $match->save();

        broadcast(new TicketUpdated($ticketId, $newStatus, null, ['match_id' => $match->id]));
    }

    private function returnPartiesToQueue(string $ticketId, ?int $penalizeUserId = null): void
    {
        $ticketKey = self::TICKET_KEY_PREFIX.$ticketId;

        $mode = $this->redisManager->hget($ticketKey, 'mode');
        $queueKey = self::QUEUE_KEYS[$mode];

        $partyIds = json_decode($this->redisManager->hget($ticketKey, 'party_ids_json') ?? '[]', true);
        if (empty($partyIds)) {
            // fallback: no-op;
            return;
        }

        foreach ($partyIds as $pid) {
            $key = self::PARTY_KEY_PREFIX.$pid;
            $hash = $this->redisManager->hgetall($key);
            if (!$hash) {
                continue;
            }

            $status = SearchStatus::Searching;

            // minor penalty: increase widen to ease re-match (or small delay if declined)
            $this->redisManager->hmset($key, [
                'status' => $status->value,
                'enqueued_at' => now()->timestamp,
            ]);
            $this->redisManager->zadd($queueKey, (int) $hash['base_mmr'], "party:{$pid}");
            broadcast(new SearchUpdated((int) $pid, $status));
        }
    }

    private function assertMode(string $mode): void
    {
        if (!in_array($mode, self::MODES, true)) {
            throw new \InvalidArgumentException("Unsupported mode: {$mode}");
        }
    }
}
