<?php

declare(strict_types=1);

namespace App\Social;

use App\Social\Broadcasting\FriendRemoved;
use App\Social\Broadcasting\FriendRequestAccepted;
use App\Social\Broadcasting\FriendRequestDeclined;
use App\Social\Broadcasting\FriendRequestReceived;
use App\Social\Broadcasting\FriendStatusChanged;
use App\Social\Models\Friendship;
use App\Social\ValueObjects\FriendshipStatus;
use App\Social\ValueObjects\RelationshipStatus;
use App\Social\ValueObjects\UserPresenceStatus;
use App\User\Models\User;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FriendshipManager
{
    private const int PRESENCE_TTL = 300; // 5 minutes

    private const int AWAY_TTL = 90; // 90 seconds

    private const int INGAME_TTL = 3600; // 1 hour

    public function __construct(
        private readonly RedisManager $redis,
    ) {}

    public function sendRequest(int $userId, int $friendId): Friendship
    {
        if ($userId === $friendId) {
            throw new \DomainException('Cannot send friend request to yourself.');
        }

        [$lock1, $lock2] = $this->lockFriendship($userId, $friendId);
        try {
            return transaction(function () use ($userId, $friendId) {
                // Check for existing relationship in either direction
                $existing = $this->findExistingRelationship($userId, $friendId);
                if ($existing) {
                    throw new \DomainException('Friendship already exists or request pending.');
                }

                $friendship = new Friendship;
                $friendship->user_id = $userId;
                $friendship->friend_id = $friendId;
                $friendship->status = FriendshipStatus::Pending->value;
                $friendship->save();

                // Broadcast to recipient
                $requester = User::findOrFail($userId);
                $recipient = User::findOrFail($friendId);
                broadcast(new FriendRequestReceived(
                    $recipient->getHashedId(),
                    $requester->getHashedId(),
                    $requester->username
                ));

                return $friendship;
            });
        } finally {
            $this->unlock($lock1);
            $this->unlock($lock2);
        }
    }

    public function acceptRequest(int $userId, int $requesterId): void
    {
        [$lock1, $lock2] = $this->lockFriendship($userId, $requesterId);
        try {
            transaction(function () use ($userId, $requesterId) {
                // Find pending request TO this user FROM requester
                $request = Friendship::where('user_id', $requesterId)
                    ->where('friend_id', $userId)
                    ->where('status', FriendshipStatus::Pending->value)
                    ->lockForUpdate()
                    ->first();

                if (!$request) {
                    throw new \DomainException('Friend request not found.');
                }

                // Update original request to accepted
                $request->status = FriendshipStatus::Accepted->value;
                $request->save();

                // Create reverse relationship
                $reverse = new Friendship;
                $reverse->user_id = $userId;
                $reverse->friend_id = $requesterId;
                $reverse->status = FriendshipStatus::Accepted->value;
                $reverse->save();

                // Broadcast to both users
                $accepter = User::findOrFail($userId);
                $requester = User::findOrFail($requesterId);

                $accepterStatus = $this->getUserPresence($userId);
                $requesterStatus = $this->getUserPresence($requesterId);

                broadcast(new FriendRequestAccepted(
                    $accepter->getHashedId(),
                    $requester->getHashedId(),
                    $requester->username,
                    $request->created_at->toISOString(),
                    $requesterStatus
                ));

                broadcast(new FriendRequestAccepted(
                    $requester->getHashedId(),
                    $accepter->getHashedId(),
                    $accepter->username,
                    $request->created_at->toISOString(),
                    $accepterStatus
                ));
            });
        } finally {
            $this->unlock($lock1);
            $this->unlock($lock2);
        }
    }

    public function declineRequest(int $userId, int $requesterId): void
    {
        [$lock1, $lock2] = $this->lockFriendship($userId, $requesterId);
        try {
            transaction(function () use ($userId, $requesterId) {
                // Find pending request TO this user FROM requester
                $request = Friendship::where('user_id', $requesterId)
                    ->where('friend_id', $userId)
                    ->where('status', FriendshipStatus::Pending->value)
                    ->lockForUpdate()
                    ->first();

                if (!$request) {
                    throw new \DomainException('Friend request not found.');
                }

                $request->delete();

                // Broadcast to requester
                $decliner = User::findOrFail($userId);
                $requester = User::findOrFail($requesterId);
                broadcast(new FriendRequestDeclined(
                    $requester->getHashedId(),
                    $decliner->getHashedId()
                ));
            });
        } finally {
            $this->unlock($lock1);
            $this->unlock($lock2);
        }
    }

    public function removeFriend(int $userId, int $friendId): void
    {
        [$lock1, $lock2] = $this->lockFriendship($userId, $friendId);
        try {
            transaction(function () use ($userId, $friendId) {
                // Delete both directions
                $deleted1 = Friendship::where('user_id', $userId)
                    ->where('friend_id', $friendId)
                    ->where('status', FriendshipStatus::Accepted->value)
                    ->delete();

                $deleted2 = Friendship::where('user_id', $friendId)
                    ->where('friend_id', $userId)
                    ->where('status', FriendshipStatus::Accepted->value)
                    ->delete();

                if (!$deleted1 && !$deleted2) {
                    throw new \DomainException('Friendship not found.');
                }

                // Broadcast to both users
                $user = User::findOrFail($userId);
                $friend = User::findOrFail($friendId);

                broadcast(new FriendRemoved(
                    $user->getHashedId(),
                    $friend->getHashedId()
                ));

                broadcast(new FriendRemoved(
                    $friend->getHashedId(),
                    $user->getHashedId()
                ));
            });
        } finally {
            $this->unlock($lock1);
            $this->unlock($lock2);
        }
    }

    public function getFriends(int $userId): Collection
    {
        return Friendship::with('friend:id,username')
            ->where('user_id', $userId)
            ->where('status', FriendshipStatus::Accepted->value)
            ->get()
            ->map(function ($friendship) {
                $presenceStatus = $this->getUserPresence($friendship->friend_id);

                return [
                    'userHash' => $friendship->friend->getHashedId(),
                    'username' => $friendship->friend->username,
                    'status' => $presenceStatus,
                    'friendsSince' => $friendship->created_at->toISOString(),
                ];
            });
    }

    public function getIncomingRequests(int $userId): Collection
    {
        return Friendship::with('user:id,username')
            ->where('friend_id', $userId)
            ->where('status', FriendshipStatus::Pending->value)
            ->get()
            ->map(fn ($friendship) => [
                'requesterHash' => $friendship->user->getHashedId(),
                'username' => $friendship->user->username,
                'requestedAt' => $friendship->created_at->toISOString(),
            ]);
    }

    public function getOutgoingRequests(int $userId): Collection
    {
        return Friendship::with('friend:id,username')
            ->where('user_id', $userId)
            ->where('status', FriendshipStatus::Pending->value)
            ->get()
            ->map(fn ($friendship) => [
                'recipientHash' => $friendship->friend->getHashedId(),
                'username' => $friendship->friend->username,
                'requestedAt' => $friendship->created_at->toISOString(),
            ]);
    }

    public function searchUsers(string $query, int $currentUserId, int $limit = 20): Collection
    {
        $users = User::where('username', 'like', "%{$query}%")
            ->where('id', '!=', $currentUserId)
            ->limit($limit)
            ->get(['id', 'username']);

        return $users->map(function ($user) use ($currentUserId) {
            $relationship = $this->getRelationshipStatus($currentUserId, $user->id);

            return [
                'userHash' => $user->getHashedId(),
                'username' => $user->username,
                'relationshipStatus' => $relationship,
            ];
        });
    }

    public function setUserPresence(int $userId, UserPresenceStatus $status): void
    {
        $key = $this->rkPresence($userId);
        $ttl = match ($status) {
            UserPresenceStatus::Away   => self::AWAY_TTL,
            UserPresenceStatus::InGame => self::INGAME_TTL,
            default                    => self::PRESENCE_TTL,
        };
        $this->redis->setex($key, $ttl, $status->value);

        // Broadcast to all friends
        $this->broadcastStatusToFriends($userId, $status->value);
    }

    public function getUserPresence(int $userId): string
    {
        $key = $this->rkPresence($userId);
        $status = $this->redis->get($key);

        return $status ?: UserPresenceStatus::Offline->value;
    }

    public function heartbeat(int $userId): void
    {
        $key = $this->rkPresence($userId);
        $currentStatus = $this->redis->get($key);

        if ($currentStatus === UserPresenceStatus::Online->value) {
            // Silently extend TTL — no broadcast needed
            $this->redis->expire($key, self::PRESENCE_TTL);
        } else {
            // Away, in-game, or no key → set online and broadcast
            $this->setUserPresence($userId, UserPresenceStatus::Online);
        }
    }

    public function setAway(int $userId): void
    {
        $key = $this->rkPresence($userId);
        $currentStatus = $this->redis->get($key);

        // Don't override in-game status
        if ($currentStatus === UserPresenceStatus::InGame->value) {
            return;
        }

        $this->redis->setex($key, self::AWAY_TTL, UserPresenceStatus::Away->value);
        $this->broadcastStatusToFriends($userId, UserPresenceStatus::Away->value);
    }

    private function findExistingRelationship(int $userId, int $friendId): ?Friendship
    {
        return Friendship::where(function ($query) use ($userId, $friendId) {
            $query->where('user_id', $userId)->where('friend_id', $friendId);
        })->orWhere(function ($query) use ($userId, $friendId) {
            $query->where('user_id', $friendId)->where('friend_id', $userId);
        })->first();
    }

    private function getRelationshipStatus(int $userId, int $otherUserId): string
    {
        $friendship = $this->findExistingRelationship($userId, $otherUserId);

        if (!$friendship) {
            return RelationshipStatus::None->value;
        }

        if ($friendship->status === FriendshipStatus::Accepted->value) {
            return RelationshipStatus::Friends->value;
        }

        if ($friendship->status === FriendshipStatus::Pending->value) {
            return $friendship->user_id === $userId
                ? RelationshipStatus::RequestSent->value
                : RelationshipStatus::RequestReceived->value;
        }

        return RelationshipStatus::None->value;
    }

    private function broadcastStatusToFriends(int $userId, string $status): void
    {
        $friends = Friendship::with('friend:id')
            ->where('user_id', $userId)
            ->where('status', FriendshipStatus::Accepted->value)
            ->get();

        $user = User::findOrFail($userId);

        foreach ($friends as $friendship) {
            broadcast(new FriendStatusChanged(
                $friendship->friend->getHashedId(),
                $user->getHashedId(),
                $status
            ));
        }
    }

    private function lockFriendship(int $userId1, int $userId2): array
    {
        // Always lock in consistent order to prevent deadlocks
        [$id1, $id2] = $userId1 < $userId2 ? [$userId1, $userId2] : [$userId2, $userId1];

        $lock1 = $this->lock($id1);
        $lock2 = $this->lock($id2);

        return [$lock1, $lock2];
    }

    private function lock(int $userId, int $ttlMs = 5000): array
    {
        $key = "social:lock:friendship:{$userId}";
        $val = Str::uuid()->toString();

        $acquired = $this->redis->set($key, $val, 'PX', $ttlMs, 'NX');
        if (!$acquired) {
            throw new \RuntimeException('User friendship operation is busy, please retry.');
        }

        return [$key, $val];
    }

    private function unlock(array $lock): void
    {
        [$key, $val] = $lock;
        try {
            $lua = <<<'LUA'
if redis.call("GET", KEYS[1]) == ARGV[1] then
  return redis.call("DEL", KEYS[1])
else
  return 0
end
LUA;
            $this->redis->eval($lua, 1, $key, $val);
        } catch (\Throwable) {
            // swallow
        }
    }

    private function rkPresence(int $userId): string
    {
        return "social:presence:user:{$userId}";
    }
}
