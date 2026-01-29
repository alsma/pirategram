<?php

declare(strict_types=1);

namespace Tests\Feature\MatchMaking;

use App\MatchMaking\MatchMakingManager;
use App\MatchMaking\PartyManager;
use App\MatchMaking\Support\MatchMakingRedisKeys;
use App\MatchMaking\ValueObjects\GameMode;
use App\MatchMaking\ValueObjects\GroupStatus;
use App\MatchMaking\ValueObjects\PartyStatus;
use App\MatchMaking\ValueObjects\TicketStatus;
use App\User\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class PartyMatchMakingTest extends TestCase
{
    private MatchMakingManager $mm;

    private PartyManager $partyManager;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->client()->flushdb();
        Queue::fake();
        $this->mm = $this->app->make(MatchMakingManager::class);
        $this->partyManager = $this->app->make(PartyManager::class);
    }

    public function test_start_party_search_creates_group_in_queue(): void
    {
        $leader = User::factory()->create(['mmr' => 1200]);
        $member = User::factory()->create(['mmr' => 1250]);

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member->id, $party);

        $sessionId = Str::uuid()->toString();
        $result = $this->mm->startPartySearch($party, $sessionId);

        $this->assertSame(GroupStatus::Searching->value, $result->state);
        $this->assertSame(GameMode::TwoVsTwo->value, $result->mode);

        $members = Redis::zrange(MatchMakingRedisKeys::QUEUE_KEYS[GameMode::TwoVsTwo->value], 0, -1) ?? [];
        $this->assertContains("party:{$party->id}", $members);
    }

    public function test_start_party_search_stores_correct_group_data(): void
    {
        $leader = User::factory()->create(['mmr' => 1200]);
        $member = User::factory()->create(['mmr' => 1300]);

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member->id, $party);

        $sessionId = Str::uuid()->toString();
        $this->mm->startPartySearch($party, $sessionId);

        $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX."party:{$party->id}";
        $hash = Redis::hgetall($hashKey);

        $this->assertSame("party:{$party->id}", $hash['group_key']);
        $this->assertSame(GameMode::TwoVsTwo->value, $hash['mode']);
        $this->assertSame('2', $hash['size']);
        $this->assertSame(GroupStatus::Searching->value, $hash['status']);

        $members = json_decode($hash['members_json'], true);
        $this->assertCount(2, $members);

        $avgMmr = (1200 + 1300) / 2;
        $this->assertSame((string) (int) round($avgMmr), $hash['mmr']);
    }

    public function test_cancel_party_search_removes_group_from_queue(): void
    {
        $leader = User::factory()->create(['mmr' => 1400]);
        $member = User::factory()->create(['mmr' => 1450]);

        $party = $this->partyManager->createParty($leader->id);
        $this->partyManager->join($member->id, $party);

        $sessionId = Str::uuid()->toString();
        $this->mm->startPartySearch($party, $sessionId);

        $this->mm->cancelPartySearch($party, $sessionId);

        $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX."party:{$party->id}";
        $this->assertEmpty(Redis::hgetall($hashKey));

        $members = Redis::zrange(MatchMakingRedisKeys::QUEUE_KEYS[GameMode::TwoVsTwo->value], 0, -1) ?? [];
        $this->assertNotContains("party:{$party->id}", $members);
    }

    public function test_two_parties_are_matched_together(): void
    {
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
        $ticket = Redis::hgetall($ticketKey);

        $this->assertSame(TicketStatus::Pending->value, $ticket['status']);
        $this->assertSame('4', $ticket['slots_total']);

        $slots = json_decode($ticket['slots_json'], true);
        $userIds = array_column($slots, 'user_id');

        $this->assertContains($leader1->id, $userIds);
        $this->assertContains($member1->id, $userIds);
        $this->assertContains($leader2->id, $userIds);
        $this->assertContains($member2->id, $userIds);
    }

    public function test_solo_player_and_party_are_matched(): void
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
        $ticket = Redis::hgetall($ticketKey);

        $this->assertSame(TicketStatus::Pending->value, $ticket['status']);
        $this->assertSame('4', $ticket['slots_total']);

        $slots = json_decode($ticket['slots_json'], true);
        $userIds = array_column($slots, 'user_id');

        // Party of 2 vs two solo players
        $this->assertContains($solo1->id, $userIds);
        $this->assertContains($solo2->id, $userIds);
        $this->assertContains($partyLeader->id, $userIds);
        $this->assertContains($partyMember->id, $userIds);
    }

    public function test_party_members_can_accept_ticket(): void
    {
        $leader1 = User::factory()->create(['mmr' => 1700]);
        $member1 = User::factory()->create(['mmr' => 1700]);
        $party1 = $this->partyManager->createParty($leader1->id);
        $this->partyManager->join($member1->id, $party1);

        $leader2 = User::factory()->create(['mmr' => 1705]);
        $member2 = User::factory()->create(['mmr' => 1705]);
        $party2 = $this->partyManager->createParty($leader2->id);
        $this->partyManager->join($member2->id, $party2);

        $session1 = Str::uuid()->toString();
        $session2 = Str::uuid()->toString();

        $this->mm->startPartySearch($party1, $session1);
        $this->mm->startPartySearch($party2, $session2);
        $this->mm->processTick();

        $ticketId = $this->getTicketIdFromGroup("party:{$party1->id}");
        $this->assertNotNull($ticketId);

        $this->mm->acceptTicket($leader1, $ticketId, $session1);
        $this->mm->acceptTicket($member1, $ticketId, $session1);

        $ticketKey = MatchMakingRedisKeys::TICKET_KEY_PREFIX.$ticketId;
        $this->assertTrue((bool) Redis::sismember("{$ticketKey}:accepted", $leader1->id));
        $this->assertTrue((bool) Redis::sismember("{$ticketKey}:accepted", $member1->id));
    }

    public function test_start_party_search_fails_when_party_is_empty(): void
    {
        $leader = User::factory()->create();
        $party = $this->partyManager->createParty($leader->id);

        // Remove the leader
        $this->partyManager->leave($leader->id, $party);

        // Recreate party in DB without members (edge case)
        $party = new \App\MatchMaking\Models\Party;
        $party->leader_id = $leader->id;
        $party->mode = GameMode::TwoVsTwo->value;
        $party->status = PartyStatus::Idle->value;
        $party->save();

        $sessionId = Str::uuid()->toString();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Party has no members');

        $this->mm->startPartySearch($party, $sessionId);
    }

    public function test_party_search_validates_session(): void
    {
        $leader = User::factory()->create(['mmr' => 1800]);
        $party = $this->partyManager->createParty($leader->id);

        $session1 = Str::uuid()->toString();
        $session2 = Str::uuid()->toString();

        $this->mm->startPartySearch($party, $session1);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\ConflictHttpException::class);
        $this->expectExceptionMessage('MULTI_TAB: Another session is active');

        $this->mm->startPartySearch($party, $session2);
    }

    private function getTicketIdFromGroup(string $groupKey): ?string
    {
        $hashKey = MatchMakingRedisKeys::GROUP_KEY_PREFIX.$groupKey;

        return Redis::hget($hashKey, 'ticket_id') ?: null;
    }
}
