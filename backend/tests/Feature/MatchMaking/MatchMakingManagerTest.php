<?php

declare(strict_types=1);

namespace Tests\Feature\MatchMaking;

use App\MatchMaking\Broadcasting\MMMatchStarted;
use App\MatchMaking\Broadcasting\MMSearchUpdated;
use App\MatchMaking\Broadcasting\MMStarting;
use App\MatchMaking\Broadcasting\MMTicketCreated;
use App\MatchMaking\Broadcasting\MMTicketExpired;
use App\MatchMaking\Jobs\MatchStartJob;
use App\MatchMaking\Jobs\TicketExpiryJob;
use App\MatchMaking\MatchMakingManager;
use App\MatchMaking\Models\GameMatch;
use App\MatchMaking\Support\MatchMakingRedisKeys;
use App\MatchMaking\ValueObjects\CancelReason;
use App\MatchMaking\ValueObjects\GameMode;
use App\MatchMaking\ValueObjects\GroupStatus;
use App\MatchMaking\ValueObjects\TicketStatus;
use App\User\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class MatchMakingManagerTest extends TestCase
{
    private MatchMakingManager $mm;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->client()->flushdb();
        Queue::fake();
        Event::fake();
        Carbon::setTestNow('2025-10-21 12:00:00');

        $this->mm = $this->app->make(MatchMakingManager::class);
    }

    public function test_start_search_creates_group_and_broadcasts(): void
    {
        $user = User::factory()->create(['mmr' => 1400]);
        $sessionId = Str::uuid()->toString();

        $result = $this->mm->startSearch($user, GameMode::OneOnOne, $sessionId);

        $this->assertSame(GroupStatus::Searching->value, $result->state);
        $this->assertSame(GameMode::OneOnOne->value, $result->mode);
        $this->assertNotNull($result->searchStartedAt);
        $this->assertNotNull($result->searchExpiresAt);

        // Check queue
        $members = $this->queueMembers(GameMode::OneOnOne->value);
        $this->assertContains("u:{$user->id}", $members);

        // Check group hash
        $groupKey = "u:{$user->id}";
        $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX.$groupKey;
        $status = Redis::hget($hashKey, 'status');
        $this->assertSame(GroupStatus::Searching->value, $status);

        // Check broadcast
        Event::assertDispatched(MMSearchUpdated::class, function (MMSearchUpdated $e) use ($user) {
            return $e->userHash === $user->getHashedId()
                && $e->payload->state === GroupStatus::Searching->value;
        });
    }

    public function test_cancel_search_removes_group_and_broadcasts(): void
    {
        $user = User::factory()->create(['mmr' => 1400]);
        $sessionId = Str::uuid()->toString();

        $this->mm->startSearch($user, GameMode::OneOnOne, $sessionId);
        $this->mm->cancelSearch($user, $sessionId);

        // Queue should be empty
        $members = $this->queueMembers(GameMode::OneOnOne->value);
        $this->assertNotContains("u:{$user->id}", $members);

        // Group hash should be deleted
        $groupKey = "u:{$user->id}";
        $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX.$groupKey;
        $hash = Redis::hgetall($hashKey);
        $this->assertEmpty($hash);

        // Check broadcast
        Event::assertDispatched(MMSearchUpdated::class, function (MMSearchUpdated $e) use ($user) {
            return $e->userHash === $user->getHashedId()
                && $e->payload->state === GroupStatus::Idle->value
                && $e->payload->reason === CancelReason::UserCancelled->value;
        });
    }

    public function test_cancel_search_from_ticket_proposed_marks_user_cancelled(): void
    {
        $user1 = User::factory()->create(['mmr' => 1400]);
        $user2 = User::factory()->create(['mmr' => 1410]);
        $session1 = Str::uuid()->toString();
        $session2 = Str::uuid()->toString();

        $this->mm->startSearch($user1, GameMode::OneOnOne, $session1);
        $this->mm->startSearch($user2, GameMode::OneOnOne, $session2);
        $this->mm->processTick();

        Event::fake();
        $this->mm->cancelSearch($user1, $session1);

        Event::assertDispatched(MMTicketExpired::class, function (MMTicketExpired $e) use ($user1) {
            return $e->userHash === $user1->getHashedId()
                && $e->payload->reason === CancelReason::UserCancelled->value
                && $e->payload->backToSearch === false;
        });

        Event::assertDispatched(MMTicketExpired::class, function (MMTicketExpired $e) use ($user2) {
            return $e->userHash === $user2->getHashedId()
                && $e->payload->reason === CancelReason::UserCancelled->value
                && $e->payload->backToSearch === true;
        });
    }

    public function test_get_state_returns_idle_when_no_search(): void
    {
        $user = User::factory()->create();

        $state = $this->mm->getState($user);

        $this->assertSame(GroupStatus::Idle->value, $state->state);
    }

    public function test_get_state_returns_searching_when_in_queue(): void
    {
        $user = User::factory()->create(['mmr' => 1500]);
        $sessionId = Str::uuid()->toString();

        $this->mm->startSearch($user, GameMode::OneOnOne, $sessionId);

        $state = $this->mm->getState($user);

        $this->assertSame(GroupStatus::Searching->value, $state->state);
        $this->assertSame(GameMode::OneOnOne->value, $state->mode);
        $this->assertNotNull($state->searchStartedAt);
        $this->assertNotNull($state->searchExpiresAt);
    }

    public function test_process_tick_forms_1v1_ticket_and_schedules_expiry(): void
    {
        $user1 = User::factory()->create(['mmr' => 1500]);
        $user2 = User::factory()->create(['mmr' => 1510]);
        $session1 = Str::uuid()->toString();
        $session2 = Str::uuid()->toString();

        $this->mm->startSearch($user1, GameMode::OneOnOne, $session1);
        $this->mm->startSearch($user2, GameMode::OneOnOne, $session2);

        Event::fake();
        $this->mm->processTick();

        // Ticket created event
        Event::assertDispatched(MMTicketCreated::class, function (MMTicketCreated $e) {
            return $e->payload->mode === GameMode::OneOnOne->value && $e->payload->slotsTotal === 2;
        });

        // Groups should be in proposed state
        $this->assertSame(GroupStatus::Proposed->value, Redis::hget(MatchMakingRedisKeys::GROUP_KEY_PREFIX."u:{$user1->id}", 'status'));
        $this->assertSame(GroupStatus::Proposed->value, Redis::hget(MatchMakingRedisKeys::GROUP_KEY_PREFIX."u:{$user2->id}", 'status'));

        // Expiry job scheduled
        Queue::assertPushed(TicketExpiryJob::class);
    }

    public function test_accept_all_confirms_ticket_and_schedules_match_start(): void
    {
        $user1 = User::factory()->create(['mmr' => 1600]);
        $user2 = User::factory()->create(['mmr' => 1605]);
        $session1 = Str::uuid()->toString();
        $session2 = Str::uuid()->toString();

        $this->mm->startSearch($user1, GameMode::OneOnOne, $session1);
        $this->mm->startSearch($user2, GameMode::OneOnOne, $session2);

        $this->mm->processTick();

        $ticketId = $this->getTicketIdFromGroup($user1);
        $this->assertNotNull($ticketId);

        Event::fake();
        $this->mm->acceptTicket($user1, $ticketId, $session1);
        $this->mm->acceptTicket($user2, $ticketId, $session2);

        // Starting broadcast sent
        Event::assertDispatched(MMStarting::class, function (MMStarting $e) use ($ticketId) {
            return $e->payload->ticketId === $ticketId;
        });

        // Match start job scheduled
        Queue::assertPushed(MatchStartJob::class, function (MatchStartJob $job) use ($ticketId) {
            return $job->ticketId === $ticketId;
        });

        // Groups should be in starting state
        $this->assertSame(GroupStatus::Starting->value, Redis::hget(MatchMakingRedisKeys::GROUP_KEY_PREFIX."u:{$user1->id}", 'status'));
        $this->assertSame(GroupStatus::Starting->value, Redis::hget(MatchMakingRedisKeys::GROUP_KEY_PREFIX."u:{$user2->id}", 'status'));
    }

    public function test_start_match_creates_game_and_broadcasts(): void
    {
        $user1 = User::factory()->create(['mmr' => 1700]);
        $user2 = User::factory()->create(['mmr' => 1705]);
        $session1 = Str::uuid()->toString();
        $session2 = Str::uuid()->toString();

        $this->mm->startSearch($user1, GameMode::OneOnOne, $session1);
        $this->mm->startSearch($user2, GameMode::OneOnOne, $session2);

        $this->mm->processTick();

        $ticketId = $this->getTicketIdFromGroup($user1);
        $this->mm->acceptTicket($user1, $ticketId, $session1);
        $this->mm->acceptTicket($user2, $ticketId, $session2);

        Event::fake();
        $this->mm->startMatch($ticketId);

        // GameMatch created for these specific users
        $match = GameMatch::query()
            ->whereJsonContains('players', $user1->id)
            ->whereJsonContains('players', $user2->id)
            ->latest()
            ->first();

        $this->assertNotNull($match);
        $this->assertSame(GameMode::OneOnOne->value, $match->mode);
        $this->assertSame('active', $match->status);
        $this->assertContains($user1->id, $match->players);
        $this->assertContains($user2->id, $match->players);

        // Match started broadcast
        Event::assertDispatched(MMMatchStarted::class, function (MMMatchStarted $e) use ($match) {
            return $e->payload->matchId === $match->id;
        });

        // Groups should be in in_match state
        $this->assertSame(GroupStatus::InMatch->value, Redis::hget(MatchMakingRedisKeys::GROUP_KEY_PREFIX."u:{$user1->id}", 'status'));
        $this->assertSame(GroupStatus::InMatch->value, Redis::hget(MatchMakingRedisKeys::GROUP_KEY_PREFIX."u:{$user2->id}", 'status'));
    }

    public function test_start_match_is_idempotent(): void
    {
        $user1 = User::factory()->create(['mmr' => 1750]);
        $user2 = User::factory()->create(['mmr' => 1755]);
        $session1 = Str::uuid()->toString();
        $session2 = Str::uuid()->toString();

        $this->mm->startSearch($user1, GameMode::OneOnOne, $session1);
        $this->mm->startSearch($user2, GameMode::OneOnOne, $session2);
        $this->mm->processTick();

        $ticketId = $this->getTicketIdFromGroup($user1);
        $this->mm->acceptTicket($user1, $ticketId, $session1);
        $this->mm->acceptTicket($user2, $ticketId, $session2);

        $this->mm->startMatch($ticketId);
        $this->mm->startMatch($ticketId);

        $matches = GameMatch::query()
            ->whereJsonContains('players', $user1->id)
            ->whereJsonContains('players', $user2->id)
            ->get();

        $this->assertCount(1, $matches);
        $this->assertSame(TicketStatus::Started->value, Redis::hget(MatchMakingRedisKeys::TICKET_KEY_PREFIX.$ticketId, 'status'));
    }

    public function test_get_state_cleans_up_missing_ticket(): void
    {
        $user1 = User::factory()->create(['mmr' => 1400]);
        $user2 = User::factory()->create(['mmr' => 1410]);
        $session1 = Str::uuid()->toString();
        $session2 = Str::uuid()->toString();

        $this->mm->startSearch($user1, GameMode::OneOnOne, $session1);
        $this->mm->startSearch($user2, GameMode::OneOnOne, $session2);
        $this->mm->processTick();

        $ticketId = $this->getTicketIdFromGroup($user1);
        Redis::del(MatchMakingRedisKeys::TICKET_KEY_PREFIX.$ticketId);

        $state = $this->mm->getState($user1);

        $this->assertSame(GroupStatus::Idle->value, $state->state);
        $this->assertEmpty(Redis::hgetall(MatchMakingRedisKeys::GROUP_KEY_PREFIX."u:{$user1->id}"));
    }

    public function test_decline_returns_other_players_to_queue(): void
    {
        $user1 = User::factory()->create(['mmr' => 1800]);
        $user2 = User::factory()->create(['mmr' => 1805]);
        $session1 = Str::uuid()->toString();
        $session2 = Str::uuid()->toString();

        $this->mm->startSearch($user1, GameMode::OneOnOne, $session1);
        $this->mm->startSearch($user2, GameMode::OneOnOne, $session2);

        $this->mm->processTick();

        $ticketId = $this->getTicketIdFromGroup($user1);
        $this->assertNotNull($ticketId);

        Event::fake();
        $this->mm->declineTicket($user2, $ticketId, $session2);

        // Ticket expired broadcast to both
        Event::assertDispatched(MMTicketExpired::class, function (MMTicketExpired $e) use ($user1, $ticketId) {
            return $e->userHash === $user1->getHashedId()
                && $e->payload->ticketId === $ticketId
                && $e->payload->reason === CancelReason::Declined->value
                && $e->payload->backToSearch === true;
        });

        Event::assertDispatched(MMTicketExpired::class, function (MMTicketExpired $e) use ($user2, $ticketId) {
            return $e->userHash === $user2->getHashedId()
                && $e->payload->ticketId === $ticketId
                && $e->payload->reason === CancelReason::Declined->value
                && $e->payload->backToSearch === false;
        });

        // User1 back to queue, user2 stopped
        $members = $this->queueMembers(GameMode::OneOnOne->value);
        $this->assertContains("u:{$user1->id}", $members);
        $this->assertNotContains("u:{$user2->id}", $members);

        // User1 back to searching
        Event::assertDispatched(MMSearchUpdated::class, function (MMSearchUpdated $e) use ($user1) {
            return $e->userHash === $user1->getHashedId()
                && $e->payload->state === GroupStatus::Searching->value;
        });
    }

    public function test_expire_ticket_returns_accepters_to_queue(): void
    {
        $user1 = User::factory()->create(['mmr' => 1900]);
        $user2 = User::factory()->create(['mmr' => 1905]);
        $session1 = Str::uuid()->toString();
        $session2 = Str::uuid()->toString();

        $this->mm->startSearch($user1, GameMode::OneOnOne, $session1);
        $this->mm->startSearch($user2, GameMode::OneOnOne, $session2);

        $this->mm->processTick();

        $ticketId = $this->getTicketIdFromGroup($user1);
        $this->assertNotNull($ticketId);

        // Only user1 accepts
        $this->mm->acceptTicket($user1, $ticketId, $session1);

        Event::fake();
        // Simulate timeout
        $this->mm->expireTicket($ticketId);

        // Ticket expired broadcast
        Event::assertDispatched(MMTicketExpired::class, function (MMTicketExpired $e) use ($ticketId) {
            return $e->payload->ticketId === $ticketId && $e->payload->reason === CancelReason::Timeout->value;
        });

        // User1 (who accepted) back to queue, user2 (who timed out) stopped
        $members = $this->queueMembers(GameMode::OneOnOne->value);
        $this->assertContains("u:{$user1->id}", $members);
        $this->assertNotContains("u:{$user2->id}", $members);
    }

    public function test_expire_ticket_stops_all_timeouts_and_returns_accepters(): void
    {
        $users = [];
        $sessions = [];
        for ($i = 0; $i < 4; $i++) {
            $users[$i] = User::factory()->create(['mmr' => 2000 + $i * 5]);
            $sessions[$i] = Str::uuid()->toString();
            $this->mm->startSearch($users[$i], GameMode::FreeForAll4, $sessions[$i]);
        }

        $this->mm->processTick();

        $ticketId = $this->getTicketIdFromGroup($users[0]);
        $this->assertNotNull($ticketId);

        // Only user0 accepts; everyone else times out
        $this->mm->acceptTicket($users[0], $ticketId, $sessions[0]);

        Event::fake();
        $this->mm->expireTicket($ticketId);

        $members = $this->queueMembers(GameMode::FreeForAll4->value);
        $this->assertContains("u:{$users[0]->id}", $members);
        $this->assertNotContains("u:{$users[1]->id}", $members);
        $this->assertNotContains("u:{$users[2]->id}", $members);
        $this->assertNotContains("u:{$users[3]->id}", $members);
    }

    public function test_widening_allows_match_after_wait(): void
    {
        $user1 = User::factory()->create(['mmr' => 1400]);
        $user2 = User::factory()->create(['mmr' => 1500]);
        $session1 = Str::uuid()->toString();
        $session2 = Str::uuid()->toString();

        $this->mm->startSearch($user1, GameMode::OneOnOne, $session1);
        $this->mm->startSearch($user2, GameMode::OneOnOne, $session2);

        Event::fake();
        // Initially no match (100 MMR diff, window is ±50)
        $this->mm->processTick();
        Event::assertNotDispatched(MMTicketCreated::class);

        // After 30 seconds, widening should allow match
        // widen: ±25 base + 3 steps * 25 = ±100
        $this->travel(30)->seconds();
        $this->mm->processTick();

        Event::assertDispatched(MMTicketCreated::class, function (MMTicketCreated $e) {
            return $e->payload->mode === GameMode::OneOnOne->value;
        });
    }

    public function test_session_validation_prevents_multi_tab(): void
    {
        $user = User::factory()->create(['mmr' => 1500]);
        $session1 = Str::uuid()->toString();
        $session2 = Str::uuid()->toString();

        $this->mm->startSearch($user, GameMode::OneOnOne, $session1);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\ConflictHttpException::class);
        $this->expectExceptionMessage('MULTI_TAB');

        $this->mm->startSearch($user, GameMode::OneOnOne, $session2);
    }

    public function test_ffa4_builds_from_four_singles(): void
    {
        $users = [];
        $sessions = [];
        for ($i = 0; $i < 4; $i++) {
            $users[$i] = User::factory()->create(['mmr' => 1400 + $i * 5]);
            $sessions[$i] = Str::uuid()->toString();
            $this->mm->startSearch($users[$i], GameMode::FreeForAll4, $sessions[$i]);
        }

        Event::fake();
        $this->mm->processTick();

        Event::assertDispatched(MMTicketCreated::class, function (MMTicketCreated $e) {
            return $e->payload->mode === GameMode::FreeForAll4->value && $e->payload->slotsTotal === 4;
        });
    }

    public function test_search_expires_after_timeout(): void
    {
        $user = User::factory()->create(['mmr' => 1500]);
        $sessionId = Str::uuid()->toString();

        $this->mm->startSearch($user, GameMode::OneOnOne, $sessionId);

        // Advance past search timeout (600 seconds)
        $this->travel(601)->seconds();

        Event::fake();
        $this->mm->processTick();

        // Group should be removed
        $members = $this->queueMembers(GameMode::OneOnOne->value);
        $this->assertNotContains("u:{$user->id}", $members);

        Event::assertDispatched(MMSearchUpdated::class, function (MMSearchUpdated $e) use ($user) {
            return $e->userHash === $user->getHashedId()
                && $e->payload->state === GroupStatus::Idle->value
                && $e->payload->reason === CancelReason::SearchTimeout->value;
        });
    }

    private function queueMembers(string $mode): array
    {
        return Redis::zrange(MatchMakingRedisKeys::QUEUE_KEYS[$mode], 0, -1) ?? [];
    }

    private function getTicketIdFromGroup(User $user): ?string
    {
        $groupKey = "u:{$user->id}";
        $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX.$groupKey;

        return Redis::hget($hashKey, 'ticket_id') ?: null;
    }
}
