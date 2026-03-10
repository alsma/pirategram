<?php

declare(strict_types=1);

namespace Tests\Api;

use App\MatchMaking\MatchMakingManager;
use App\MatchMaking\PartyManager;
use App\MatchMaking\Support\MatchMakingRedisKeys;
use App\MatchMaking\ValueObjects\GameMode;
use App\MatchMaking\ValueObjects\GroupStatus;
use App\User\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class PartyMatchMakingApiTest extends TestCase
{
    private PartyManager $partyManager;

    private MatchMakingManager $mm;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->client()->flushdb();
        Queue::fake();
        $this->partyManager = $this->app->make(PartyManager::class);
        $this->mm = $this->app->make(MatchMakingManager::class);
    }

    public function test_start_party_search_endpoint_creates_group(): void
    {
        $leader = User::factory()->create(['mmr' => 1200]);
        $member = User::factory()->create(['mmr' => 1250]);

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member->id, $party);

        $sessionId = Str::uuid()->toString();

        $this->actingAs($leader, 'sanctum');
        $response = $this->postJson('/api/mm/party/search/start', [
            'partyHash' => $party->getHashedId(),
            'sessionId' => $sessionId,
        ]);

        $response->assertOk()->assertJson([
            'state' => GroupStatus::Searching->value,
            'mode' => GameMode::TwoVsTwo->value,
            'sessionId' => $sessionId,
        ]);

        $members = Redis::zrange(MatchMakingRedisKeys::QUEUE_KEYS[GameMode::TwoVsTwo->value], 0, -1) ?? [];
        $this->assertContains("party:{$party->id}", $members);
    }

    public function test_start_party_search_endpoint_fails_for_non_leader(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member->id, $party);

        $sessionId = Str::uuid()->toString();

        $this->actingAs($member, 'sanctum');
        $response = $this->postJson('/api/mm/party/search/start', [
            'partyHash' => $party->getHashedId(),
            'sessionId' => $sessionId,
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Only the party leader can perform this action.']);
    }

    public function test_cancel_party_search_endpoint_removes_group(): void
    {
        $leader = User::factory()->create(['mmr' => 1300]);
        $member = User::factory()->create(['mmr' => 1350]);

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member->id, $party);

        $sessionId = Str::uuid()->toString();
        $this->mm->startPartySearch($party, $sessionId);

        $this->actingAs($leader, 'sanctum');
        $response = $this->postJson('/api/mm/party/search/cancel', [
            'sessionId' => $sessionId,
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX."party:{$party->id}";
        $this->assertEmpty(Redis::hgetall($hashKey));
    }

    public function test_cancel_party_search_endpoint_fails_for_non_leader(): void
    {
        $leader = User::factory()->create(['mmr' => 1400]);
        $member = User::factory()->create(['mmr' => 1450]);

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member->id, $party);

        $sessionId = Str::uuid()->toString();
        $this->mm->startPartySearch($party, $sessionId);

        $this->actingAs($member, 'sanctum');
        $response = $this->postJson('/api/mm/party/search/cancel', [
            'sessionId' => $sessionId,
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Only the party leader can perform this action.']);
    }

    public function test_cancel_party_search_endpoint_fails_when_not_in_party(): void
    {
        $user = User::factory()->create();
        $sessionId = Str::uuid()->toString();

        $this->actingAs($user, 'sanctum');
        $response = $this->postJson('/api/mm/party/search/cancel', [
            'sessionId' => $sessionId,
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Not in a party']);
    }

    public function test_parties_are_matched_together(): void
    {
        // Create two 2v2 parties
        $leader1 = User::factory()->create(['mmr' => 1500]);
        $member1 = User::factory()->create(['mmr' => 1500]);
        $party1 = $this->partyManager->createParty($leader1->id);
        $this->partyManager->join($member1->id, $party1);

        $leader2 = User::factory()->create(['mmr' => 1505]);
        $member2 = User::factory()->create(['mmr' => 1505]);
        $party2 = $this->partyManager->createParty($leader2->id);
        $this->partyManager->join($member2->id, $party2);

        $session1 = Str::uuid()->toString();
        $session2 = Str::uuid()->toString();

        $this->mm->startPartySearch($party1, $session1);
        $this->mm->startPartySearch($party2, $session2);

        $this->mm->processTick();

        $ticketId = $this->getTicketIdFromGroup("party:{$party1->id}");
        $this->assertNotNull($ticketId);

        $ticketKey = MatchMakingRedisKeys::TICKET_KEY_PREFIX.$ticketId;
        $slots = json_decode(Redis::hget($ticketKey, 'slots_json') ?? '[]', true);

        $userIds = array_column($slots, 'user_id');
        $this->assertContains($leader1->id, $userIds);
        $this->assertContains($member1->id, $userIds);
        $this->assertContains($leader2->id, $userIds);
        $this->assertContains($member2->id, $userIds);
    }

    public function test_solo_and_party_can_be_matched_together(): void
    {
        // Create two solo players
        $solo1 = User::factory()->create(['mmr' => 1600]);
        $solo2 = User::factory()->create(['mmr' => 1605]);

        // Create a party of 2
        $partyLeader = User::factory()->create(['mmr' => 1600]);
        $partyMember = User::factory()->create(['mmr' => 1605]);
        $party = $this->partyManager->createParty($partyLeader->id);
        $this->partyManager->join($partyMember->id, $party);

        $sessionSolo1 = Str::uuid()->toString();
        $sessionSolo2 = Str::uuid()->toString();
        $sessionParty = Str::uuid()->toString();

        // Start searches
        $this->mm->startSearch($solo1, GameMode::TwoVsTwo, $sessionSolo1);
        $this->mm->startSearch($solo2, GameMode::TwoVsTwo, $sessionSolo2);
        $this->mm->startPartySearch($party, $sessionParty);

        $this->mm->processTick();

        $ticketId = $this->getTicketIdFromGroup("u:{$solo1->id}");
        $this->assertNotNull($ticketId);

        $ticketKey = MatchMakingRedisKeys::TICKET_KEY_PREFIX.$ticketId;
        $slots = json_decode(Redis::hget($ticketKey, 'slots_json') ?? '[]', true);

        $userIds = array_column($slots, 'user_id');

        // Party of 2 vs two solo players (total 4 players for 2v2)
        $this->assertContains($solo1->id, $userIds);
        $this->assertContains($solo2->id, $userIds);
        $this->assertContains($partyLeader->id, $userIds);
        $this->assertContains($partyMember->id, $userIds);
    }

    private function getTicketIdFromGroup(string $groupKey): ?string
    {
        $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX.$groupKey;

        return Redis::hget($hashKey, 'ticket_id') ?: null;
    }
}
