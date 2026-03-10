<?php

declare(strict_types=1);

namespace Tests\Api;

use App\MatchMaking\PartyManager;
use App\MatchMaking\ValueObjects\GameMode;
use App\User\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class PartyManagementApiTest extends TestCase
{
    private PartyManager $partyManager;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->client()->flushdb();
        Queue::fake();
        $this->partyManager = $this->app->make(PartyManager::class);
    }

    public function test_kick_endpoint_removes_member_from_party(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->setMode($leader->id, $party, GameMode::TwoVsTwo->value);
        $party->refresh();
        $this->partyManager->join($member->id, $party);

        $this->actingAs($leader, 'sanctum');
        $response = $this->postJson('/api/mm/party/kick', [
            'memberUserId' => $member->getHashedId(),
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
    }

    public function test_kick_endpoint_fails_for_non_leader(): void
    {
        $leader = User::factory()->create();
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->setMode($leader->id, $party, GameMode::TwoVsTwo->value);
        $party->refresh();
        $this->partyManager->join($member1->id, $party);
        $this->partyManager->join($member2->id, $party);

        $this->actingAs($member1, 'sanctum');
        $response = $this->postJson('/api/mm/party/kick', [
            'memberUserId' => $member2->getHashedId(),
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Only the party leader can perform this action.']);
    }

    public function test_kick_endpoint_fails_when_leader_kicks_self(): void
    {
        $leader = User::factory()->create();
        $party = $this->partyManager->createParty($leader->id);

        $this->actingAs($leader, 'sanctum');
        $response = $this->postJson('/api/mm/party/kick', [
            'memberUserId' => $leader->getHashedId(),
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Leader cannot kick self.']);
    }

    public function test_promote_endpoint_changes_party_leader(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member->id, $party);

        $this->actingAs($leader, 'sanctum');
        $response = $this->postJson('/api/mm/party/promote', [
            'newLeaderUserId' => $member->getHashedId(),
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $party->refresh();
        $this->assertSame($member->id, $party->leader_id);
    }

    public function test_promote_endpoint_fails_for_non_leader(): void
    {
        $leader = User::factory()->create();
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->setMode($leader->id, $party, GameMode::TwoVsTwo->value);
        $party->refresh();
        $this->partyManager->join($member1->id, $party);
        $this->partyManager->join($member2->id, $party);

        $this->actingAs($member1, 'sanctum');
        $response = $this->postJson('/api/mm/party/promote', [
            'newLeaderUserId' => $member2->getHashedId(),
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Only the party leader can perform this action.']);
    }

    public function test_promote_endpoint_fails_for_non_member(): void
    {
        $leader = User::factory()->create();
        $nonMember = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);

        $this->actingAs($leader, 'sanctum');
        $response = $this->postJson('/api/mm/party/promote', [
            'newLeaderUserId' => $nonMember->getHashedId(),
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'User is not in the party.']);
    }
}
