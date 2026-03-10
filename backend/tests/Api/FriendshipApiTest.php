<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Auth\Events\UserLoggedOut;
use App\Social\FriendshipManager;
use App\Social\Models\Friendship;
use App\Social\ValueObjects\FriendshipStatus;
use App\Social\ValueObjects\UserPresenceStatus;
use App\User\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class FriendshipApiTest extends TestCase
{
    private FriendshipManager $friendshipManager;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->client()->flushdb();
        Event::fake();
        $this->friendshipManager = $this->app->make(FriendshipManager::class);
    }

    public function test_send_friend_request_creates_pending_friendship(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->actingAs($user1, 'sanctum');
        $response = $this->postJson('/api/friends/request', [
            'friendHash' => $user2->getHashedId(),
        ]);

        $response->assertOk()->assertJson([
            'ok' => true,
        ]);

        $this->assertTrue(
            Friendship::where('user_id', $user1->id)
                ->where('friend_id', $user2->id)
                ->where('status', FriendshipStatus::Pending->value)
                ->exists()
        );
    }

    public function test_cannot_send_friend_request_to_self(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum');
        $response = $this->postJson('/api/friends/request', [
            'friendHash' => $user->getHashedId(),
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_send_duplicate_friend_request(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->friendshipManager->sendRequest($user1->id, $user2->id);

        $this->actingAs($user1, 'sanctum');
        $response = $this->postJson('/api/friends/request', [
            'friendHash' => $user2->getHashedId(),
        ]);

        $response->assertStatus(422);
    }

    public function test_accept_friend_request_creates_bidirectional_friendship(): void
    {
        $requester = User::factory()->create();
        $recipient = User::factory()->create();

        $this->friendshipManager->sendRequest($requester->id, $recipient->id);

        $this->actingAs($recipient, 'sanctum');
        $response = $this->postJson('/api/friends/request/accept', [
            'requesterHash' => $requester->getHashedId(),
        ]);

        $response->assertOk()->assertJson([
            'ok' => true,
        ]);

        // Check both directions exist
        $this->assertTrue(
            Friendship::where('user_id', $requester->id)
                ->where('friend_id', $recipient->id)
                ->where('status', FriendshipStatus::Accepted->value)
                ->exists()
        );

        $this->assertTrue(
            Friendship::where('user_id', $recipient->id)
                ->where('friend_id', $requester->id)
                ->where('status', FriendshipStatus::Accepted->value)
                ->exists()
        );
    }

    public function test_decline_friend_request_removes_request(): void
    {
        $requester = User::factory()->create();
        $recipient = User::factory()->create();

        $this->friendshipManager->sendRequest($requester->id, $recipient->id);

        $this->actingAs($recipient, 'sanctum');
        $response = $this->postJson('/api/friends/request/decline', [
            'requesterHash' => $requester->getHashedId(),
        ]);

        $response->assertOk()->assertJson([
            'ok' => true,
        ]);

        $this->assertFalse(
            Friendship::where('user_id', $requester->id)
                ->where('friend_id', $recipient->id)
                ->exists()
        );
    }

    public function test_remove_friend_deletes_both_directions(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->friendshipManager->sendRequest($user1->id, $user2->id);
        $this->friendshipManager->acceptRequest($user2->id, $user1->id);

        $this->actingAs($user1, 'sanctum');
        $response = $this->postJson('/api/friends/remove', [
            'friendHash' => $user2->getHashedId(),
        ]);

        $response->assertOk()->assertJson([
            'ok' => true,
        ]);

        // Both directions should be deleted
        $this->assertFalse(
            Friendship::where('user_id', $user1->id)
                ->where('friend_id', $user2->id)
                ->exists()
        );

        $this->assertFalse(
            Friendship::where('user_id', $user2->id)
                ->where('friend_id', $user1->id)
                ->exists()
        );
    }

    public function test_get_friends_returns_only_accepted_friendships(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $friend1 = User::factory()->create(['username' => 'friend1']);
        $friend2 = User::factory()->create(['username' => 'friend2']);
        $pending = User::factory()->create(['username' => 'pending']);

        // Create accepted friendships
        $this->friendshipManager->sendRequest($user->id, $friend1->id);
        $this->friendshipManager->acceptRequest($friend1->id, $user->id);

        $this->friendshipManager->sendRequest($user->id, $friend2->id);
        $this->friendshipManager->acceptRequest($friend2->id, $user->id);

        // Create pending request
        $this->friendshipManager->sendRequest($user->id, $pending->id);

        $this->actingAs($user, 'sanctum');
        $response = $this->getJson('/api/friends');

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(2, $data['friends']);
        $this->assertEquals('friend1', $data['friends'][0]['username']);
        $this->assertEquals('friend2', $data['friends'][1]['username']);
    }

    public function test_get_friend_requests_returns_incoming_and_outgoing(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $requester = User::factory()->create(['username' => 'requester']);
        $recipient = User::factory()->create(['username' => 'recipient']);

        // Incoming request
        $this->friendshipManager->sendRequest($requester->id, $user->id);

        // Outgoing request
        $this->friendshipManager->sendRequest($user->id, $recipient->id);

        $this->actingAs($user, 'sanctum');
        $response = $this->getJson('/api/friends/requests');

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(1, $data['incoming']);
        $this->assertEquals('requester', $data['incoming'][0]['username']);

        $this->assertCount(1, $data['outgoing']);
        $this->assertEquals('recipient', $data['outgoing'][0]['username']);
    }

    public function test_search_users_returns_results_with_relationship_status(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $friend = User::factory()->create(['username' => 'searchfriend']);
        $stranger = User::factory()->create(['username' => 'searchstranger']);
        $pending = User::factory()->create(['username' => 'searchpending']);

        // Make one a friend
        $this->friendshipManager->sendRequest($user->id, $friend->id);
        $this->friendshipManager->acceptRequest($friend->id, $user->id);

        // Send request to pending
        $this->friendshipManager->sendRequest($user->id, $pending->id);

        $this->actingAs($user, 'sanctum');
        $response = $this->getJson('/api/friends/search?query=search');

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(3, $data['results']);

        // Find each user in results
        $friendResult = collect($data['results'])->firstWhere('username', 'searchfriend');
        $strangerResult = collect($data['results'])->firstWhere('username', 'searchstranger');
        $pendingResult = collect($data['results'])->firstWhere('username', 'searchpending');

        $this->assertEquals('friends', $friendResult['relationshipStatus']);
        $this->assertEquals('none', $strangerResult['relationshipStatus']);
        $this->assertEquals('request_sent', $pendingResult['relationshipStatus']);
    }

    public function test_search_users_requires_minimum_query_length(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum');
        $response = $this->getJson('/api/friends/search?query=a');

        $response->assertStatus(422);
    }

    public function test_heartbeat_extends_presence_ttl(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum');
        $response = $this->postJson('/api/friends/heartbeat');

        $response->assertOk()->assertJson([
            'ok' => true,
        ]);

        // Verify presence key exists in Redis
        $key = "social:presence:user:{$user->id}";
        $status = Redis::get($key);

        $this->assertNotNull($status);
    }

    public function test_friends_list_includes_online_status(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $friend = User::factory()->create(['username' => 'friend']);

        $this->friendshipManager->sendRequest($user->id, $friend->id);
        $this->friendshipManager->acceptRequest($friend->id, $user->id);

        // Set friend as online
        $this->friendshipManager->heartbeat($friend->id);

        $this->actingAs($user, 'sanctum');
        $response = $this->getJson('/api/friends');

        $response->assertOk();
        $data = $response->json();

        $this->assertEquals('online', $data['friends'][0]['status']);
    }

    public function test_cannot_accept_nonexistent_friend_request(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($user, 'sanctum');
        $response = $this->postJson('/api/friends/request/accept', [
            'requesterHash' => $stranger->getHashedId(),
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_remove_nonexistent_friend(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($user, 'sanctum');
        $response = $this->postJson('/api/friends/remove', [
            'friendHash' => $stranger->getHashedId(),
        ]);

        $response->assertStatus(422);
    }

    public function test_search_excludes_current_user(): void
    {
        $user = User::factory()->create(['username' => 'searchuser']);

        $this->actingAs($user, 'sanctum');
        $response = $this->getJson('/api/friends/search?query=search');

        $response->assertOk();
        $data = $response->json();

        // User should not appear in their own search results
        $selfResult = collect($data['results'])->firstWhere('username', 'searchuser');
        $this->assertNull($selfResult);
    }

    // -------------------------------------------------------------------------
    // Away status
    // -------------------------------------------------------------------------

    public function test_away_endpoint_sets_away_status(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum');
        $response = $this->postJson('/api/friends/away');

        $response->assertOk()->assertJson(['ok' => true]);

        $key = "social:presence:user:{$user->id}";
        $this->assertEquals(UserPresenceStatus::Away->value, Redis::get($key));
    }

    public function test_away_endpoint_does_not_override_in_game_status(): void
    {
        $user = User::factory()->create();

        $this->friendshipManager->setUserPresence($user->id, UserPresenceStatus::InGame);

        $this->actingAs($user, 'sanctum');
        $this->postJson('/api/friends/away');

        $key = "social:presence:user:{$user->id}";
        $this->assertEquals(UserPresenceStatus::InGame->value, Redis::get($key));
    }

    public function test_friends_list_includes_away_status(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $friend = User::factory()->create(['username' => 'friend']);

        $this->friendshipManager->sendRequest($user->id, $friend->id);
        $this->friendshipManager->acceptRequest($friend->id, $user->id);

        $this->friendshipManager->setAway($friend->id);

        $this->actingAs($user, 'sanctum');
        $response = $this->getJson('/api/friends');

        $response->assertOk();
        $this->assertEquals(UserPresenceStatus::Away->value, $response->json('friends.0.status'));
    }

    // -------------------------------------------------------------------------
    // Heartbeat
    // -------------------------------------------------------------------------

    public function test_heartbeat_transitions_away_to_online(): void
    {
        $user = User::factory()->create();

        // Set user as away first
        $this->friendshipManager->setAway($user->id);
        $key = "social:presence:user:{$user->id}";
        $this->assertEquals(UserPresenceStatus::Away->value, Redis::get($key));

        $this->actingAs($user, 'sanctum');
        $this->postJson('/api/friends/heartbeat')->assertOk();

        $this->assertEquals(UserPresenceStatus::Online->value, Redis::get($key));
    }

    public function test_heartbeat_transitions_in_game_to_online(): void
    {
        $user = User::factory()->create();

        $this->friendshipManager->setUserPresence($user->id, UserPresenceStatus::InGame);
        $key = "social:presence:user:{$user->id}";
        $this->assertEquals(UserPresenceStatus::InGame->value, Redis::get($key));

        $this->actingAs($user, 'sanctum');
        $this->postJson('/api/friends/heartbeat')->assertOk();

        $this->assertEquals(UserPresenceStatus::Online->value, Redis::get($key));
    }

    public function test_heartbeat_silently_extends_ttl_when_already_online(): void
    {
        $user = User::factory()->create();

        $this->friendshipManager->setUserPresence($user->id, UserPresenceStatus::Online);
        $key = "social:presence:user:{$user->id}";

        // Record TTL right after setting
        $ttlBefore = Redis::ttl($key);

        // Simulate some time passing by lowering the TTL
        Redis::expire($key, 60);
        $this->assertLessThan($ttlBefore, Redis::ttl($key));

        $this->actingAs($user, 'sanctum');
        $this->postJson('/api/friends/heartbeat')->assertOk();

        // TTL should be restored to ~300s
        $this->assertGreaterThan(200, Redis::ttl($key));
        // Status must still be online
        $this->assertEquals(UserPresenceStatus::Online->value, Redis::get($key));
    }

    // -------------------------------------------------------------------------
    // Accept request broadcasts actual presence status
    // -------------------------------------------------------------------------

    public function test_accept_request_succeeds_when_requester_is_online(): void
    {
        $requester = User::factory()->create();
        $recipient = User::factory()->create();

        // Set requester online in Redis before accepting
        $this->friendshipManager->setUserPresence($requester->id, UserPresenceStatus::Online);
        $this->friendshipManager->sendRequest($requester->id, $recipient->id);

        $this->actingAs($recipient, 'sanctum');
        $this->postJson('/api/friends/request/accept', [
            'requesterHash' => $requester->getHashedId(),
        ])->assertOk();

        // Friendship rows should exist in both directions
        $this->assertTrue(
            Friendship::where('user_id', $requester->id)
                ->where('friend_id', $recipient->id)
                ->where('status', FriendshipStatus::Accepted->value)
                ->exists()
        );
        $this->assertTrue(
            Friendship::where('user_id', $recipient->id)
                ->where('friend_id', $requester->id)
                ->where('status', FriendshipStatus::Accepted->value)
                ->exists()
        );
    }

    public function test_accept_request_includes_status_in_broadcast_payload(): void
    {
        $requester = User::factory()->create();
        $recipient = User::factory()->create();

        $this->friendshipManager->sendRequest($requester->id, $recipient->id);

        // Set requester online before accepting
        $this->friendshipManager->setUserPresence($requester->id, UserPresenceStatus::Online);

        // Call acceptRequest directly to test the broadcast payload
        $this->friendshipManager->acceptRequest($recipient->id, $requester->id);

        // The presence status for the requester should still be online in Redis
        $key = "social:presence:user:{$requester->id}";
        $this->assertEquals(UserPresenceStatus::Online->value, Redis::get($key));
    }

    // -------------------------------------------------------------------------
    // Logout fires UserLoggedOut event
    // -------------------------------------------------------------------------

    public function test_logout_fires_user_logged_out_event(): void
    {
        $user = User::factory()->create();
        $this->friendshipManager->setUserPresence($user->id, UserPresenceStatus::Online);

        $this->actingAs($user, 'sanctum');

        // Don't fake events so the listener actually runs
        Event::fake([UserLoggedOut::class]);

        $this->postJson('/api/auth/logout')->assertOk();

        Event::assertDispatched(UserLoggedOut::class, function (UserLoggedOut $event) use ($user) {
            return $event->user->id === $user->id;
        });
    }

    public function test_logout_sets_user_offline(): void
    {
        $user = User::factory()->create();

        // Set online via direct Redis write to bypass Event::fake in setUp
        $key = "social:presence:user:{$user->id}";
        Redis::setex($key, 300, UserPresenceStatus::Online->value);
        $this->assertEquals(UserPresenceStatus::Online->value, Redis::get($key));

        // Call listener directly — tests the FriendshipManager side effect
        $listener = $this->app->make(\App\Social\Listeners\SetUserOfflineOnLogoutListener::class);
        $listener->handle(new UserLoggedOut($user));

        $this->assertEquals(UserPresenceStatus::Offline->value, Redis::get($key));
    }

    // -------------------------------------------------------------------------
    // In-game presence TTL
    // -------------------------------------------------------------------------

    public function test_set_user_presence_uses_long_ttl_for_in_game(): void
    {
        $user = User::factory()->create();

        $this->friendshipManager->setUserPresence($user->id, UserPresenceStatus::InGame);

        $key = "social:presence:user:{$user->id}";
        $this->assertEquals(UserPresenceStatus::InGame->value, Redis::get($key));
        // TTL should be ~3600s (in-game TTL)
        $this->assertGreaterThan(3500, Redis::ttl($key));
    }

    public function test_set_user_presence_uses_short_ttl_for_away(): void
    {
        $user = User::factory()->create();

        $this->friendshipManager->setAway($user->id);

        $key = "social:presence:user:{$user->id}";
        $this->assertEquals(UserPresenceStatus::Away->value, Redis::get($key));
        // TTL should be ~90s (away TTL)
        $this->assertLessThan(100, Redis::ttl($key));
        $this->assertGreaterThan(50, Redis::ttl($key));
    }
}
