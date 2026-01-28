<?php

declare(strict_types=1);

namespace Tests\Api;

use App\MatchMaking\MatchMakingManager;
use App\MatchMaking\Support\MatchMakingRedisKeys;
use App\MatchMaking\ValueObjects\GameMode;
use App\MatchMaking\ValueObjects\GroupStatus;
use App\MatchMaking\ValueObjects\TicketStatus;
use App\User\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class MatchMakingApiTest extends TestCase
{
    private MatchMakingManager $mm;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->client()->flushdb();
        Queue::fake();
        $this->mm = $this->app->make(MatchMakingManager::class);
    }

    public function test_start_search_endpoint_creates_group(): void
    {
        $user = User::factory()->create(['mmr' => 1200]);
        $this->actingAs($user, 'sanctum');
        $sessionId = Str::uuid()->toString();

        $response = $this->postJson('/api/mm/search/start', [
            'mode' => GameMode::OneOnOne->value,
            'sessionId' => $sessionId,
        ]);

        $response->assertOk()->assertJson([
            'state' => GroupStatus::Searching->value,
            'mode' => GameMode::OneOnOne->value,
            'sessionId' => $sessionId,
        ]);

        $members = Redis::zrange(MatchMakingRedisKeys::QUEUE_KEYS[GameMode::OneOnOne->value], 0, -1) ?? [];
        $this->assertContains("u:{$user->id}", $members);
    }

    public function test_cancel_search_endpoint_removes_group(): void
    {
        $user = User::factory()->create(['mmr' => 1300]);
        $sessionId = Str::uuid()->toString();
        $this->mm->startSearch($user, GameMode::OneOnOne, $sessionId);
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/mm/search/cancel', [
            'sessionId' => $sessionId,
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX."u:{$user->id}";
        $this->assertEmpty(Redis::hgetall($hashKey));
    }

    public function test_get_state_endpoint_returns_searching(): void
    {
        $user = User::factory()->create(['mmr' => 1400]);
        $sessionId = Str::uuid()->toString();
        $this->mm->startSearch($user, GameMode::OneOnOne, $sessionId);
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/mm/state');

        $response->assertOk()->assertJson([
            'state' => GroupStatus::Searching->value,
            'mode' => GameMode::OneOnOne->value,
        ]);
    }

    public function test_accept_ticket_endpoint_marks_user_accepted(): void
    {
        $user1 = User::factory()->create(['mmr' => 1500]);
        $user2 = User::factory()->create(['mmr' => 1505]);
        $session1 = Str::uuid()->toString();
        $session2 = Str::uuid()->toString();

        $this->mm->startSearch($user1, GameMode::OneOnOne, $session1);
        $this->mm->startSearch($user2, GameMode::OneOnOne, $session2);
        $this->mm->processTick();

        $ticketId = $this->getTicketIdFromGroup($user1);
        $this->assertNotNull($ticketId);

        $this->actingAs($user1, 'sanctum');
        $response = $this->postJson("/api/mm/ticket/{$ticketId}/accept", [
            'sessionId' => $session1,
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $ticketKey = MatchMakingRedisKeys::TICKET_KEY_PREFIX.$ticketId;
        $this->assertTrue((bool) Redis::sismember("{$ticketKey}:accepted", $user1->id));
    }

    public function test_decline_ticket_endpoint_cancels_ticket(): void
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

        $this->actingAs($user2, 'sanctum');
        $response = $this->postJson("/api/mm/ticket/{$ticketId}/decline", [
            'sessionId' => $session2,
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $ticketKey = MatchMakingRedisKeys::TICKET_KEY_PREFIX.$ticketId;
        $this->assertSame(TicketStatus::Cancelled->value, Redis::hget($ticketKey, 'status'));
    }

    private function getTicketIdFromGroup(User $user): ?string
    {
        $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX."u:{$user->id}";

        return Redis::hget($hashKey, 'ticket_id') ?: null;
    }
}
