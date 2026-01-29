<?php

declare(strict_types=1);

namespace Tests\Api;

use App\MatchMaking\Models\Party;
use App\MatchMaking\Models\PartyMember;
use App\MatchMaking\PartyManager;
use App\MatchMaking\ValueObjects\GameMode;
use App\User\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class PartyApiTest extends TestCase
{
    private PartyManager $partyManager;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->client()->flushdb();
        Queue::fake();
        $this->partyManager = $this->app->make(PartyManager::class);
    }

    public function test_join_party_endpoint_adds_user_to_party(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);

        $this->actingAs($member, 'sanctum');
        $response = $this->postJson('/api/mm/party/join', [
            'partyId' => $party->id,
        ]);

        $response->assertOk()->assertJson([
            'ok' => true,
        ]);

        $this->assertTrue(
            PartyMember::where('party_id', $party->id)
                ->where('user_id', $member->id)
                ->exists()
        );
    }

    public function test_join_party_endpoint_fails_for_nonexistent_party(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum');
        $response = $this->postJson('/api/mm/party/join', [
            'partyId' => 99999,
        ]);

        $response->assertUnprocessable();
    }

    public function test_join_party_endpoint_removes_user_from_previous_party(): void
    {
        $leader1 = User::factory()->create();
        $leader2 = User::factory()->create();
        $member = User::factory()->create();

        $party1 = $this->partyManager->createParty($leader1->id);
        $party2 = $this->partyManager->createParty($leader2->id);

        $this->partyManager->join($member->id, $party1);

        $this->actingAs($member, 'sanctum');
        $response = $this->postJson('/api/mm/party/join', [
            'partyId' => $party2->id,
        ]);

        $response->assertStatus(422)->assertJson([
            'message' => 'User already in a party.',
        ]);
    }

    public function test_leave_party_endpoint_removes_user_from_party(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member->id, $party);

        $this->actingAs($member, 'sanctum');
        $response = $this->postJson('/api/mm/party/leave', [
            'partyId' => $party->id,
        ]);

        $response->assertOk()->assertJson([
            'ok' => true,
        ]);

        $this->assertFalse(
            PartyMember::where('party_id', $party->id)
                ->where('user_id', $member->id)
                ->exists()
        );
    }

    public function test_leave_party_endpoint_transfers_leadership_when_leader_leaves(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member->id, $party);

        $this->actingAs($leader, 'sanctum');
        $response = $this->postJson('/api/mm/party/leave', [
            'partyId' => $party->id,
        ]);

        $response->assertOk();

        $party->refresh();
        $this->assertSame($member->id, $party->leader_id);
    }

    public function test_leave_party_endpoint_disbands_party_when_last_member_leaves(): void
    {
        $leader = User::factory()->create();
        $party = $this->partyManager->createParty($leader->id);

        $this->actingAs($leader, 'sanctum');
        $response = $this->postJson('/api/mm/party/leave', [
            'partyId' => $party->id,
        ]);

        $response->assertOk();

        $this->assertNull(Party::find($party->id));
    }

    public function test_join_party_endpoint_fails_when_party_is_full(): void
    {
        $leader = User::factory()->create();
        $party = $this->partyManager->createParty($leader->id);

        $member1 = User::factory()->create();
        $this->partyManager->join($member1->id, $party);
        $member2 = User::factory()->create();
        $this->partyManager->join($member2->id, $party);
        $member3 = User::factory()->create();
        $this->partyManager->join($member3->id, $party);

        // Try to join when full
        $member4 = User::factory()->create();
        $this->actingAs($member4, 'sanctum');

        $response = $this->postJson('/api/mm/party/join', [
            'partyId' => $party->id,
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Party is full for mode '.GameMode::TwoVsTwo->value.'.']);
    }
}
