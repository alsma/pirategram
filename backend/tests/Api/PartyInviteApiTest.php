<?php

declare(strict_types=1);

namespace Tests\Api;

use App\MatchMaking\Models\PartyMember;
use App\MatchMaking\PartyManager;
use App\User\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class PartyInviteApiTest extends TestCase
{
    private PartyManager $partyManager;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->client()->flushdb();
        Queue::fake();
        $this->partyManager = $this->app->make(PartyManager::class);
    }

    public function test_create_party_invite_endpoint_stores_invite(): void
    {
        $leader = User::factory()->create();
        $invitee = User::factory()->create();

        $this->actingAs($leader, 'sanctum');
        $response = $this->postJson('/api/mm/party/invite', [
            'userId' => $invitee->getHashedId(),
            'mode' => \App\MatchMaking\ValueObjects\GameMode::TwoVsTwo->value,
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertNotNull(Redis::get("mm:invite:user:{$invitee->id}:leader:{$leader->id}"));
    }

    public function test_accept_party_invite_endpoint_adds_user_to_party(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $this->partyManager->createInvite($leader->id, $member->id, \App\MatchMaking\ValueObjects\GameMode::TwoVsTwo->value);

        $this->actingAs($member, 'sanctum');
        $response = $this->postJson('/api/mm/party/invite/accept', [
            'leaderId' => $leader->getHashedId(),
        ]);

        $response->assertOk()->assertJsonStructure([
            'partyHash',
            'leaderId',
            'leaderHash',
            'mode',
            'members' => [
                '*' => ['userId', 'userHash', 'username'],
            ],
            'maxPlayers',
        ]);

        $party = \App\MatchMaking\Models\Party::where('leader_id', $leader->id)->firstOrFail();
        $this->assertTrue(
            PartyMember::where('party_id', $party->id)
                ->where('user_id', $member->id)
                ->exists()
        );

        // Verify the response contains the correct party data
        $response->assertJson([
            'leaderId' => $leader->id,
            'leaderHash' => $leader->getHashedId(),
            'mode' => \App\MatchMaking\ValueObjects\GameMode::TwoVsTwo->value,
        ]);
    }

    public function test_accept_party_invite_endpoint_fails_for_invalid_code(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $this->actingAs($member, 'sanctum');
        $response = $this->postJson('/api/mm/party/invite/accept', [
            'leaderId' => $leader->getHashedId(),
        ]);

        $response->assertStatus(422)->assertJson([
            'message' => 'Invite code is invalid or expired.',
        ]);
    }

    public function test_accept_party_invite_endpoint_fails_when_user_already_in_party(): void
    {
        $leader = User::factory()->create();
        $otherLeader = User::factory()->create();
        $member = User::factory()->create();

        $otherParty = $this->partyManager->createParty($otherLeader->id);
        $this->partyManager->join($member->id, $otherParty);
        $this->partyManager->createInvite($leader->id, $member->id, \App\MatchMaking\ValueObjects\GameMode::TwoVsTwo->value);

        $this->actingAs($member, 'sanctum');
        $response = $this->postJson('/api/mm/party/invite/accept', [
            'leaderId' => $leader->getHashedId(),
        ]);

        $response->assertStatus(422)->assertJson([
            'message' => 'User already in a party.',
        ]);
    }

    public function test_decline_party_invite_endpoint_removes_invite(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $this->partyManager->createInvite($leader->id, $member->id, \App\MatchMaking\ValueObjects\GameMode::TwoVsTwo->value);

        $this->actingAs($member, 'sanctum');
        $response = $this->postJson('/api/mm/party/invite/decline', [
            'leaderId' => $leader->getHashedId(),
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertNull(Redis::get("mm:invite:user:{$member->id}:leader:{$leader->id}"));
    }

    public function test_decline_party_invite_endpoint_fails_for_invalid_invite(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $this->actingAs($member, 'sanctum');
        $response = $this->postJson('/api/mm/party/invite/decline', [
            'leaderId' => $leader->getHashedId(),
        ]);

        $response->assertStatus(422)->assertJson([
            'message' => 'Invite code is invalid or expired.',
        ]);
    }

    public function test_duplicate_party_invite_returns_ok_without_error(): void
    {
        $leader = User::factory()->create();
        $invitee = User::factory()->create();

        $this->actingAs($leader, 'sanctum');

        // Send first invite
        $response1 = $this->postJson('/api/mm/party/invite', [
            'userId' => $invitee->getHashedId(),
            'mode' => \App\MatchMaking\ValueObjects\GameMode::TwoVsTwo->value,
        ]);
        $response1->assertOk();

        // Send duplicate invite - should return 200 without error
        $response2 = $this->postJson('/api/mm/party/invite', [
            'userId' => $invitee->getHashedId(),
            'mode' => \App\MatchMaking\ValueObjects\GameMode::TwoVsTwo->value,
        ]);
        $response2->assertOk()->assertJson(['ok' => true]);

        // Verify only one invite exists in Redis
        $inviteKey = "mm:invite:user:{$invitee->id}:leader:{$leader->id}";
        $this->assertNotNull(Redis::get($inviteKey));
    }
}
