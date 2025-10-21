<?php

declare(strict_types=1);

namespace Tests\Feature\MatchMaking;

use App\MatchMaking\Broadcasting\SearchUpdated;
use App\MatchMaking\Broadcasting\TicketCreated;
use App\MatchMaking\Broadcasting\TicketUpdated;
use App\MatchMaking\Jobs\TicketTimeoutJob;
use App\MatchMaking\MatchMakingManager;
use App\MatchMaking\Models\GameMatch;
use App\MatchMaking\ValueObjects\SearchStatus;
use App\MatchMaking\ValueObjects\TicketStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class MatchMakingManagerTest extends TestCase
{
    private MatchMakingManager $matchMakingManager;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->client()->flushdb();
        Queue::fake();
        Event::fake();
        Carbon::setTestNow('2025-10-21 12:00:00');

        $this->matchMakingManager = $this->app->make(MatchMakingManager::class);
    }

    public function test_start_search_enqueues_party_and_broadcasts(): void
    {
        $this->matchMakingManager->startSearch($this->party(
            101, 10, [['id' => 10, 'mmr' => 1400]], '1v1'
        ));

        $members = $this->queueMembers('1v1');
        $this->assertContains('party:101', $members);

        // Party status = Searching
        $status = Redis::hget(MatchMakingManager::PARTY_KEY_PREFIX.'101', 'status');
        $this->assertSame(SearchStatus::Searching->value, $status);

        // Event dispatched
        Event::assertDispatched(SearchUpdated::class, function (SearchUpdated $e) {
            return $e->partyId === 101 && $e->status === SearchStatus::Searching;
        });
    }

    public function test_process_tick_forms_1v1_ticket_and_schedules_timeout(): void
    {
        $this->matchMakingManager->startSearch($this->party(201, 20, [['id' => 20, 'mmr' => 1500]], '1v1'));
        $this->matchMakingManager->startSearch($this->party(202, 21, [['id' => 21, 'mmr' => 1510]], '1v1'));

        $this->matchMakingManager->processTick();

        $created = $this->lastTicketCreated();
        $this->assertNotNull($created);
        $this->assertSame('1v1', $created->mode);

        $this->assertSame(SearchStatus::Matched->value, Redis::hget(MatchMakingManager::PARTY_KEY_PREFIX.'201', 'status'));
        $this->assertSame(SearchStatus::Matched->value, Redis::hget(MatchMakingManager::PARTY_KEY_PREFIX.'202', 'status'));

        Queue::assertPushed(TicketTimeoutJob::class, function (TicketTimeoutJob $job) use ($created) {
            return $job->ticketId === $created->ticketId;
        });
    }

    public function test_accept_all_finalizes_ticket_and_creates_game_match(): void
    {
        $this->matchMakingManager->startSearch($this->party(301, 30, [['id' => 30, 'mmr' => 1600]], '1v1'));
        $this->matchMakingManager->startSearch($this->party(302, 31, [['id' => 31, 'mmr' => 1605]], '1v1'));

        $this->matchMakingManager->processTick();

        $ticket = $this->lastTicketCreated();
        $this->assertNotNull($ticket);
        $ticketId = $ticket->ticketId;

        // Accept both
        $this->matchMakingManager->acceptTicket(30, $ticketId);
        $this->matchMakingManager->acceptTicket(31, $ticketId);

        // Confirmed broadcast with match_id
        Event::assertDispatched(TicketUpdated::class, function (TicketUpdated $e) {
            return $e->status === TicketStatus::Confirmed && isset($e->extra['match_id']);
        });

        // DB: GameMatch saved
        $this->assertDatabaseCount((new GameMatch)->getTable(), 1);
        $match = GameMatch::query()->first();
        $this->assertSame('1v1', $match->mode);
        $this->assertSame('ready', $match->status);
        $this->assertContains(30, $match->players);
        $this->assertContains(31, $match->players);
    }

    public function test_decline_returns_parties_to_queue_and_broadcasts(): void
    {
        $this->matchMakingManager->startSearch($this->party(401, 40, [['id' => 40, 'mmr' => 1700]], '1v1'));
        $this->matchMakingManager->startSearch($this->party(402, 41, [['id' => 41, 'mmr' => 1708]], '1v1'));
        $this->matchMakingManager->processTick();

        $ticket = $this->lastTicketCreated();
        $this->assertNotNull($ticket);

        // One declines
        $this->matchMakingManager->declineTicket(41, $ticket->ticketId);

        // TicketUpdated Declined
        Event::assertDispatched(TicketUpdated::class, function (TicketUpdated $e) use ($ticket) {
            return $e->ticketId === $ticket->ticketId && $e->status === TicketStatus::Declined;
        });

        // Both parties back to queue
        $members = $this->queueMembers('1v1');
        $this->assertContains('party:401', $members);
        $this->assertContains('party:402', $members);

        // SearchUpdated back to Searching
        Event::assertDispatched(SearchUpdated::class, function (SearchUpdated $e) {
            return in_array($e->partyId, [401, 402], true) && $e->status === SearchStatus::Searching;
        });
    }

    public function test_timeout_moves_parties_back_to_queue_and_broadcasts(): void
    {
        $this->matchMakingManager->startSearch($this->party(501, 50, [['id' => 50, 'mmr' => 1800]], '1v1'));
        $this->matchMakingManager->startSearch($this->party(502, 51, [['id' => 51, 'mmr' => 1802]], '1v1'));

        $this->matchMakingManager->processTick();
        $ticket = $this->lastTicketCreated();
        $this->assertNotNull($ticket);

        // Simulate job
        $this->matchMakingManager->timeoutTicket($ticket->ticketId);

        Event::assertDispatched(TicketUpdated::class, function (TicketUpdated $e) use ($ticket) {
            return $e->ticketId === $ticket->ticketId && $e->status === TicketStatus::Timeout;
        });

        $members = $this->queueMembers('1v1');
        $this->assertContains('party:501', $members);
        $this->assertContains('party:502', $members);
    }

    public function test_two_v_two_can_be_built_from_two_plus_two_singles(): void
    {
        // Duo
        $this->matchMakingManager->startSearch($this->party(601, 60, [
            ['id' => 60, 'mmr' => 1550],
            ['id' => 61, 'mmr' => 1540],
        ], '2v2'));

        // Singles
        $this->matchMakingManager->startSearch($this->party(602, 62, [['id' => 62, 'mmr' => 1545]], '2v2'));
        $this->matchMakingManager->startSearch($this->party(603, 63, [['id' => 63, 'mmr' => 1555]], '2v2'));

        $this->matchMakingManager->processTick();

        Event::assertDispatched(TicketCreated::class, function (TicketCreated $e) {
            if ($e->mode !== '2v2') {
                return false;
            }

            $flat = array_merge(...$e->teams);
            sort($flat);

            return $flat === [60, 61, 62, 63];
        });
    }

    public function test_ffa4_builds_from_four_singles(): void
    {
        $this->matchMakingManager->startSearch($this->party(701, 70, [['id' => 70, 'mmr' => 1400]], 'ffa4'));
        $this->matchMakingManager->startSearch($this->party(702, 71, [['id' => 71, 'mmr' => 1410]], 'ffa4'));
        $this->matchMakingManager->startSearch($this->party(703, 72, [['id' => 72, 'mmr' => 1420]], 'ffa4'));
        $this->matchMakingManager->startSearch($this->party(704, 73, [['id' => 73, 'mmr' => 1415]], 'ffa4'));

        $this->matchMakingManager->processTick();

        Event::assertDispatched(TicketCreated::class, function (TicketCreated $e) {
            if ($e->mode !== 'ffa4') {
                return false;
            }

            // FFA sends a single "team" list with 4 players by current implementation
            $this->assertCount(1, $e->teams);
            $this->assertCount(4, $e->teams[0]);
            $flat = $e->teams[0];
            sort($flat);

            return $flat === [70, 71, 72, 73];
        });
    }

    public function test_widening_allows_match_after_wait_when_initially_no_overlap(): void
    {
        /*
         * Config in the class:
         * - widenStepSeconds = 10
         * - widenPerStep = 25
         *
         * Two singles: 1400 and 1500
         * At t=0:
         *   A: [1400, 1400], B: [1500, 1500] -> no overlap
         * After 20s (2 steps):
         *   A: [1350, 1450], B: [1450, 1550] -> overlap at 1450
         */
        $this->matchMakingManager->startSearch($this->party(801, 80, [['id' => 80, 'mmr' => 1400]], '1v1'));
        $this->matchMakingManager->startSearch($this->party(802, 81, [['id' => 81, 'mmr' => 1500]], '1v1'));

        // Initially, no match
        $this->matchMakingManager->processTick();
        Event::assertNotDispatched(TicketCreated::class);

        // Advance time by 20 seconds to create overlap
        $this->travel(20)->seconds();

        $this->matchMakingManager->processTick();

        // Now a ticket should be created
        Event::assertDispatched(TicketCreated::class, function (TicketCreated $e) {
            return $e->mode === '1v1'
                && in_array(80, $e->teams[0] ?? [], true) || in_array(80, $e->teams[1] ?? [], true);
        });
    }

    public function test_widening_enables_ffa4_when_initially_spread_out(): void
    {
        /*
         * Start with four singles spread so there is no initial all-pair overlap.
         * MMRs: 1300, 1400, 1500, 1600
         * After 50s (4 steps => ±100), windows will be:
         *   [1200..1400], [1300..1500], [1400..1600], [1500..1700]
         * Global overlap band at 1400..1400 (edge case overlap point).
         */
        $this->matchMakingManager->startSearch($this->party(901, 90, [['id' => 90, 'mmr' => 1300]], 'ffa4'));
        $this->matchMakingManager->startSearch($this->party(902, 91, [['id' => 91, 'mmr' => 1400]], 'ffa4'));
        $this->matchMakingManager->startSearch($this->party(903, 92, [['id' => 92, 'mmr' => 1500]], 'ffa4'));
        $this->matchMakingManager->startSearch($this->party(904, 93, [['id' => 93, 'mmr' => 1600]], 'ffa4'));

        // No match at t=0
        $this->matchMakingManager->processTick();
        Event::assertNotDispatched(TicketCreated::class);

        // After 50 seconds, pairwise/allOverlap should succeed
        $this->travel(50)->seconds();
        $this->matchMakingManager->processTick();

        Event::assertDispatched(TicketCreated::class, function (TicketCreated $e) {
            return $e->mode === 'ffa4' && count($e->teams) === 1 && count($e->teams[0]) === 4;
        });
    }

    private function party(
        int $partyId,
        int $leaderId,
        array $members, // each: ['id' => int, 'mmr' => int]
        string $mode
    ): array {
        return [
            'party_id' => $partyId,
            'leader_id' => $leaderId,
            'members' => $members,
            'mode' => $mode,
        ];
    }

    private function queueMembers(string $mode): array
    {
        return Redis::zrange(MatchMakingManager::QUEUE_KEYS[$mode], 0, -1) ?? [];
    }

    private function lastTicketCreated(): ?TicketCreated
    {
        $found = null;
        Event::assertDispatched(TicketCreated::class, function (TicketCreated $e) use (&$found) {
            $found = $e;

            return true;
        });

        return $found;
    }
}
