<?php

declare(strict_types=1);

namespace Tests\Feature\MatchMaking;

use App\MatchMaking\Models\Party;
use App\MatchMaking\Models\PartyMember;
use App\MatchMaking\PartyManager;
use App\MatchMaking\Support\MatchMakingRedisKeys;
use App\MatchMaking\ValueObjects\GameMode;
use App\MatchMaking\ValueObjects\PartyStatus;
use App\User\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class PartyManagerTest extends TestCase
{
    private PartyManager $partyManager;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->client()->flushdb();
        Queue::fake();
        $this->partyManager = $this->app->make(PartyManager::class);
    }

    public function test_create_party_creates_party_with_leader(): void
    {
        $leader = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);

        $this->assertInstanceOf(Party::class, $party);
        $this->assertSame($leader->id, $party->leader_id);
        $this->assertSame(GameMode::TwoVsTwo->value, $party->mode);
        $this->assertSame(PartyStatus::Idle->value, $party->status);

        $this->assertTrue(
            PartyMember::where('party_id', $party->id)
                ->where('user_id', $leader->id)
                ->exists()
        );
    }

    public function test_create_party_removes_user_from_existing_party(): void
    {
        $leader = User::factory()->create();

        $party1 = $this->partyManager->createParty($leader->id);
        $party2 = $this->partyManager->createParty($leader->id);

        $this->assertFalse(
            PartyMember::where('party_id', $party1->id)
                ->where('user_id', $leader->id)
                ->exists()
        );

        $this->assertTrue(
            PartyMember::where('party_id', $party2->id)
                ->where('user_id', $leader->id)
                ->exists()
        );
    }

    public function test_join_adds_user_to_party(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member->id, $party);

        $this->assertTrue(
            PartyMember::where('party_id', $party->id)
                ->where('user_id', $member->id)
                ->exists()
        );
    }

    public function test_join_fails_when_user_already_in_party(): void
    {
        $leader1 = User::factory()->create();
        $leader2 = User::factory()->create();
        $member = User::factory()->create();

        $party1 = $this->partyManager->createParty($leader1->id);
        $party2 = $this->partyManager->createParty($leader2->id);

        $this->partyManager->join($member->id, $party1);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User already in a party.');
        $this->partyManager->join($member->id, $party2);
    }

    public function test_join_fails_when_party_is_full(): void
    {
        $leader = User::factory()->create();
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();
        $member3 = User::factory()->create();
        $member4 = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member1->id, $party);
        $this->partyManager->join($member2->id, $party);
        $this->partyManager->join($member3->id, $party);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Party is full for mode '.GameMode::TwoVsTwo->value.'.');

        $this->partyManager->join($member4->id, $party);
    }

    public function test_leave_removes_user_from_party(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member->id, $party);

        $this->partyManager->leave($member->id, $party);

        $this->assertFalse(
            PartyMember::where('party_id', $party->id)
                ->where('user_id', $member->id)
                ->exists()
        );
    }

    public function test_leave_transfers_leadership_when_leader_leaves(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member->id, $party);

        $this->partyManager->leave($leader->id, $party);

        $party->refresh();
        $this->assertSame($member->id, $party->leader_id);
    }

    public function test_leave_disbands_party_when_last_member_leaves(): void
    {
        $leader = User::factory()->create();
        $party = $this->partyManager->createParty($leader->id);

        $this->partyManager->leave($leader->id, $party);

        $this->assertNull(Party::find($party->id));
    }

    public function test_disband_deletes_party_and_members(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member->id, $party);

        $this->partyManager->disband($leader->id, $party);

        $this->assertNull(Party::find($party->id));
        $this->assertFalse(PartyMember::where('party_id', $party->id)->exists());
    }

    public function test_disband_fails_for_non_leader(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member->id, $party);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only the party leader can perform this action');

        $this->partyManager->disband($member->id, $party);
    }

    public function test_kick_removes_member_from_party(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member->id, $party);

        $this->partyManager->kick($leader->id, $party, $member->id);

        $this->assertFalse(
            PartyMember::where('party_id', $party->id)
                ->where('user_id', $member->id)
                ->exists()
        );
    }

    public function test_kick_fails_for_non_leader(): void
    {
        $leader = User::factory()->create();
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member1->id, $party);
        $this->partyManager->join($member2->id, $party);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only the party leader can perform this action');

        $this->partyManager->kick($member1->id, $party, $member2->id);
    }

    public function test_kick_fails_when_leader_tries_to_kick_self(): void
    {
        $leader = User::factory()->create();
        $party = $this->partyManager->createParty($leader->id);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Leader cannot kick self.');

        $this->partyManager->kick($leader->id, $party, $leader->id);
    }

    public function test_promote_changes_party_leader(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member->id, $party);

        $this->partyManager->promote($leader->id, $party, $member->id);

        $party->refresh();
        $this->assertSame($member->id, $party->leader_id);
    }

    public function test_promote_fails_for_non_leader(): void
    {
        $leader = User::factory()->create();
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->setMode($leader->id, $party, GameMode::TwoVsTwo->value);
        $this->partyManager->join($member1->id, $party);
        $this->partyManager->join($member2->id, $party);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only the party leader can perform this action');

        $this->partyManager->promote($member1->id, $party, $member2->id);
    }

    public function test_promote_fails_for_non_member(): void
    {
        $leader = User::factory()->create();
        $nonMember = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User is not in the party');

        $this->partyManager->promote($leader->id, $party, $nonMember->id);
    }

    public function test_set_mode_changes_party_mode(): void
    {
        $leader = User::factory()->create();
        $party = $this->partyManager->createParty($leader->id);

        $this->partyManager->setMode($leader->id, $party, GameMode::TwoVsTwo->value);

        $party->refresh();
        $this->assertSame(GameMode::TwoVsTwo->value, $party->mode);
    }

    public function test_set_mode_fails_for_non_leader(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member->id, $party);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only the party leader can perform this action.');

        $this->partyManager->setMode($member->id, $party, GameMode::TwoVsTwo->value);
    }

    public function test_set_mode_fails_for_unsupported_mode(): void
    {
        $leader = User::factory()->create();
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member1->id, $party);
        $this->partyManager->join($member2->id, $party);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported mode');

        $this->partyManager->setMode($leader->id, $party, GameMode::OneOnOne->value);
    }

    public function test_sync_redis_party_stores_party_data(): void
    {
        $leader = User::factory()->create(['mmr' => 1200]);
        $member = User::factory()->create(['mmr' => 1300]);

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member->id, $party);

        $this->partyManager->syncRedisParty($party->id);

        $redisKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX."party:{$party->id}";
        $hash = Redis::hgetall($redisKey);

        $this->assertSame("party:{$party->id}", $hash['group_key']);
        $this->assertSame((string) $party->id, $hash['party_id']);
        $this->assertSame((string) $leader->id, $hash['leader_id']);
        $this->assertSame('2', $hash['size']);
        $this->assertSame(GameMode::TwoVsTwo->value, $hash['mode']);

        $members = json_decode($hash['members_json'], true);
        $this->assertCount(2, $members);
    }

    public function test_create_invite_stores_invite(): void
    {
        $leader = User::factory()->create();
        $invitee = User::factory()->create();

        $this->partyManager->createInvite($leader->id, $invitee->id, GameMode::TwoVsTwo->value);

        $redisKey = "mm:invite:user:{$invitee->id}:leader:{$leader->id}";
        $payload = Redis::get($redisKey);
        $this->assertNotNull($payload);

        $data = json_decode($payload, true);
        $this->assertSame($leader->id, $data['leader_id']);
        $this->assertSame(GameMode::TwoVsTwo->value, $data['mode']);
    }

    public function test_accept_invite_adds_user_to_party(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $this->partyManager->createInvite($leader->id, $member->id, GameMode::TwoVsTwo->value);

        $this->partyManager->acceptInvite($member->id, $leader->id);

        $party = \App\MatchMaking\Models\Party::where('leader_id', $leader->id)->firstOrFail();
        $this->assertSame(GameMode::TwoVsTwo->value, $party->mode);

        $this->assertTrue(
            PartyMember::where('party_id', $party->id)
                ->where('user_id', $member->id)
                ->exists()
        );
    }

    public function test_user_can_accept_one_of_multiple_invites(): void
    {
        $leader1 = User::factory()->create();
        $leader2 = User::factory()->create();
        $member = User::factory()->create();

        $this->partyManager->createInvite($leader1->id, $member->id, GameMode::TwoVsTwo->value);
        $this->partyManager->createInvite($leader2->id, $member->id, GameMode::TwoVsTwo->value);

        // Verify both invites exist before accepting
        $this->assertNotNull(Redis::get("mm:invite:user:{$member->id}:leader:{$leader1->id}"));
        $this->assertNotNull(Redis::get("mm:invite:user:{$member->id}:leader:{$leader2->id}"));

        $this->partyManager->acceptInvite($member->id, $leader1->id);

        $party = \App\MatchMaking\Models\Party::where('leader_id', $leader1->id)->firstOrFail();
        $this->assertTrue(
            PartyMember::where('party_id', $party->id)
                ->where('user_id', $member->id)
                ->exists()
        );

        // Both invites should be cleared after accepting one
        $this->assertNull(Redis::get("mm:invite:user:{$member->id}:leader:{$leader1->id}"));
        $this->assertNull(Redis::get("mm:invite:user:{$member->id}:leader:{$leader2->id}"));
    }

    public function test_accept_invite_fails_for_invalid_code(): void
    {
        $member = User::factory()->create();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Invite code is invalid or expired');

        $this->partyManager->acceptInvite($member->id, 9999);
    }

    public function test_accept_invite_fails_when_user_already_in_party(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();
        $otherLeader = User::factory()->create();

        $this->partyManager->createParty($otherLeader->id);
        $this->partyManager->createInvite($leader->id, $member->id, GameMode::TwoVsTwo->value);

        $this->partyManager->join($member->id, \App\MatchMaking\Models\Party::where('leader_id', $otherLeader->id)->firstOrFail());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User already in a party.');

        $this->partyManager->acceptInvite($member->id, $leader->id);
    }

    public function test_decline_invite_removes_invite(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $this->partyManager->createInvite($leader->id, $member->id, GameMode::TwoVsTwo->value);

        $redisKey = "mm:invite:user:{$member->id}:leader:{$leader->id}";
        $this->assertNotNull(Redis::get($redisKey));

        $this->partyManager->declineInvite($member->id, $leader->id);

        $this->assertNull(Redis::get($redisKey));
    }

    public function test_decline_invite_fails_for_invalid_invite(): void
    {
        $member = User::factory()->create();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Invite code is invalid or expired');

        $this->partyManager->declineInvite($member->id, 9999);
    }

    public function test_decline_invite_does_not_affect_other_invites(): void
    {
        $leader1 = User::factory()->create();
        $leader2 = User::factory()->create();
        $member = User::factory()->create();

        $this->partyManager->createInvite($leader1->id, $member->id, GameMode::TwoVsTwo->value);
        $this->partyManager->createInvite($leader2->id, $member->id, GameMode::TwoVsTwo->value);

        $this->partyManager->declineInvite($member->id, $leader1->id);

        $this->assertNull(Redis::get("mm:invite:user:{$member->id}:leader:{$leader1->id}"));
        $this->assertNotNull(Redis::get("mm:invite:user:{$member->id}:leader:{$leader2->id}"));
    }

    public function test_create_invite_fails_when_party_is_searching(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);

        // Simulate party in searching state
        Redis::hmset(MatchMakingRedisKeys::GROUP_KEY_PREFIX."party:{$party->id}", [
            'status' => 'searching',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Matchmaking is active for this party');

        $this->partyManager->createInvite($leader->id, $member->id, GameMode::TwoVsTwo->value);
    }

    public function test_get_user_party_returns_party_when_user_is_member(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member->id, $party);

        $memberParty = $this->partyManager->getUserParty($member->id);

        $this->assertNotNull($memberParty);
        $this->assertSame($party->id, $memberParty->id);
    }

    public function test_get_user_party_returns_null_when_user_not_in_party(): void
    {
        $user = User::factory()->create();

        $party = $this->partyManager->getUserParty($user->id);

        $this->assertNull($party);
    }

    public function test_ensure_is_leader_passes_for_leader(): void
    {
        $leader = User::factory()->create();
        $party = $this->partyManager->createParty($leader->id);

        $this->partyManager->ensureIsLeader($party, $leader->id);

        $this->assertTrue(true); // No exception thrown
    }

    public function test_ensure_is_leader_fails_for_non_leader(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member->id, $party);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only the party leader can perform this action');

        $this->partyManager->ensureIsLeader($party, $member->id);
    }
}
