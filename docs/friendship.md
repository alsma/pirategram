# Friendship / Social System

The social system handles friend relationships, friend requests, user search, and online presence. It is fully independent of matchmaking — a user does not need to be friends with someone to be in a party with them, though the friends list is the primary UI surface for sending party invites to known players.

---

## Directory Structure

```
backend/app/Social/
├── FriendshipManager.php                     # Core service
├── Models/
│   └── Friendship.php
├── Http/
│   ├── Controllers/
│   │   ├── GetFriendsController.php
│   │   ├── GetFriendRequestsController.php
│   │   ├── SendFriendRequestController.php
│   │   ├── AcceptFriendRequestController.php
│   │   ├── DeclineFriendRequestController.php
│   │   ├── RemoveFriendController.php
│   │   ├── SearchUsersController.php
│   │   ├── HeartbeatController.php
│   │   └── SetAwayController.php
│   ├── Requests/
│   │   ├── SendFriendRequestRequest.php
│   │   ├── AcceptFriendRequestRequest.php
│   │   ├── DeclineFriendRequestRequest.php
│   │   ├── RemoveFriendRequest.php
│   │   └── SearchUsersRequest.php
│   └── Resources/
│       ├── FriendshipOkResource.php
│       ├── FriendsListResource.php
│       ├── FriendRequestsResource.php
│       └── UserSearchResource.php
├── Broadcasting/
│   ├── FriendRequestReceived.php
│   ├── FriendRequestAccepted.php
│   ├── FriendRequestDeclined.php
│   ├── FriendRemoved.php
│   └── FriendStatusChanged.php
├── Listeners/
│   ├── SetUserOfflineOnLogoutListener.php    # Handles Auth\Events\UserLoggedOut
│   └── SetUsersInGameOnMatchStartListener.php # Handles MatchMaking\Events\MatchStarted
└── ValueObjects/
    ├── FriendshipStatus.php
    ├── RelationshipStatus.php
    ├── UserPresenceStatus.php
    └── FriendAction.php

backend/app/Auth/Events/
└── UserLoggedOut.php                         # Fired by AuthManager::logout()

backend/app/MatchMaking/Events/
└── MatchStarted.php                          # Fired by MatchMakingManager::startMatch()

frontend/
├── api/friends.js
├── store/friends-store.js
├── components/social/friends-sidebar.jsx
└── lib/constants/social.js
```

---

## Database Schema

### `friendships`

| Column       | Type      | Notes                                             |
|--------------|-----------|---------------------------------------------------|
| `id`         | bigint PK |                                                   |
| `user_id`    | bigint FK | References `users.id`                             |
| `friend_id`  | bigint FK | References `users.id`                             |
| `status`     | string    | `pending`, `accepted`, or `blocked`               |
| `created_at` | timestamp |                                                   |
| `updated_at` | timestamp |                                                   |

**Constraints and indexes:**
- `UNIQUE(user_id, friend_id)` — prevents duplicate directional rows
- Index on `(user_id, status)` — fast lookup of a user's friends/pending requests
- Index on `(friend_id, status)` — fast lookup of incoming requests

**Bidirectional design:** An accepted friendship is stored as two rows:
- `(user_id=Alice, friend_id=Bob, status=accepted)`
- `(user_id=Bob, friend_id=Alice, status=accepted)`

This allows efficient `WHERE user_id = ?` queries for the friends list and presence lookups without joins. Both rows are created atomically when a request is accepted, and both are deleted when a friendship is removed.

A pending request is stored as a single row from the requester:
- `(user_id=Alice, friend_id=Bob, status=pending)` — Alice sent Bob a request

---

## Enums / Value Objects

### `FriendshipStatus` (`backend/app/Social/ValueObjects/FriendshipStatus.php`)

| Case       | Value      | Notes                                  |
|------------|------------|----------------------------------------|
| `Pending`  | `pending`  | Request sent, not yet accepted         |
| `Accepted` | `accepted` | Active friendship (bidirectional rows) |
| `Blocked`  | `blocked`  | Reserved for future use                |

### `RelationshipStatus` (`backend/app/Social/ValueObjects/RelationshipStatus.php`)

Used in user search results to show the current user's relationship with each result.

| Case              | Value              | Meaning                                    |
|-------------------|--------------------|--------------------------------------------|
| `None`            | `none`             | No relationship                            |
| `Friends`         | `friends`          | Both are friends                           |
| `RequestSent`     | `request_sent`     | Current user sent a request to this user   |
| `RequestReceived` | `request_received` | This user sent a request to the current user |

### `UserPresenceStatus` (`backend/app/Social/ValueObjects/UserPresenceStatus.php`)

| Case      | Value     | Redis TTL | Notes                                                        |
|-----------|-----------|-----------|--------------------------------------------------------------|
| `Online`  | `online`  | 300s      | User has sent a heartbeat within the TTL                     |
| `Offline` | `offline` | —         | No presence key in Redis (expired, never set, or logged out) |
| `Away`    | `away`    | 90s       | Tab is hidden; set via `POST /api/friends/away`              |
| `InGame`  | `in-game` | 3600s     | Set by matchmaking when a match starts; not overridable by heartbeat or away |

### `FriendAction` (`backend/app/Social/ValueObjects/FriendAction.php`)

Event action constants — currently used for frontend reference.

| Case              | Value             |
|-------------------|-------------------|
| `RequestSent`     | `requestSent`     |
| `RequestAccepted` | `requestAccepted` |
| `RequestDeclined` | `requestDeclined` |
| `FriendRemoved`   | `friendRemoved`   |
| `StatusChanged`   | `statusChanged`   |

---

## API Routes

All routes are under prefix `/api/friends` and require `auth:sanctum`.

| Method | Path                          | Controller                       | Description                                  |
|--------|-------------------------------|----------------------------------|----------------------------------------------|
| GET    | `/api/friends`                | `GetFriendsController`           | List accepted friends with presence status   |
| GET    | `/api/friends/requests`       | `GetFriendRequestsController`    | List incoming and outgoing pending requests  |
| GET    | `/api/friends/search`         | `SearchUsersController`          | Search users by username with relationship status |
| POST   | `/api/friends/request`        | `SendFriendRequestController`    | Send a friend request                        |
| POST   | `/api/friends/request/accept` | `AcceptFriendRequestController`  | Accept an incoming friend request            |
| POST   | `/api/friends/request/decline`| `DeclineFriendRequestController` | Decline an incoming friend request           |
| POST   | `/api/friends/remove`         | `RemoveFriendController`         | Remove an existing friendship                |
| POST   | `/api/friends/heartbeat`      | `HeartbeatController`            | Extend presence TTL or restore online status (sent every 120s) |
| POST   | `/api/friends/away`           | `SetAwayController`              | Mark user as away (called on tab hide)       |

### Request / Response Shapes

User identifiers are always **hashed IDs** (Hashids). Form request classes resolve them to integer IDs.

**GET `/api/friends`** — `FriendsListResource`
```json
{
  "friends": [
    {
      "userHash": "abc123",
      "username": "Bob",
      "status": "online",
      "friendsSince": "2026-01-15T10:00:00.000Z"
    }
  ]
}
```

**GET `/api/friends/requests`** — `FriendRequestsResource`
```json
{
  "incoming": [
    { "requesterHash": "def456", "username": "Charlie", "requestedAt": "..." }
  ],
  "outgoing": [
    { "recipientHash": "ghi789", "username": "Dana", "requestedAt": "..." }
  ]
}
```

**GET `/api/friends/search?query=alice`** — `UserSearchResource`
```json
{
  "results": [
    { "userHash": "jkl012", "username": "Alice", "relationshipStatus": "none" }
  ]
}
```
Results exclude the current user. Default limit: 20.

**POST `/api/friends/request`**
```json
{ "friendHash": "<targetUserHash>" }
```

**POST `/api/friends/request/accept`**
```json
{ "requesterHash": "<requesterHash>" }
```

**POST `/api/friends/request/decline`**
```json
{ "requesterHash": "<requesterHash>" }
```

**POST `/api/friends/remove`**
```json
{ "friendHash": "<friendHash>" }
```

All mutation endpoints return `FriendshipOkResource`:
```json
{ "ok": true }
```

---

## Service: `FriendshipManager`

Location: `backend/app/Social/FriendshipManager.php`

Injected dependencies: `RedisManager`.

TTL constants:

| Constant       | Value  | Used for                        |
|----------------|--------|---------------------------------|
| `PRESENCE_TTL` | 300s   | `online` status                 |
| `AWAY_TTL`     | 90s    | `away` status                   |
| `INGAME_TTL`   | 3600s  | `in-game` status                |

### Public Methods

#### `sendRequest(int $userId, int $friendId): Friendship`
- Prevents self-request.
- Acquires ordered friendship lock for both IDs (deadlock prevention).
- Checks for any existing relationship in either direction (`findExistingRelationship`).
- Creates a `Friendship(user_id=userId, friend_id=friendId, status=pending)`.
- Broadcasts `FriendRequestReceived` to the recipient's personal channel.

#### `acceptRequest(int $userId, int $requesterId): void`
- Acquires ordered friendship lock.
- Finds the `pending` request from `requesterId → userId` (locks the row).
- Updates it to `accepted`.
- Creates the reverse row `(userId → requesterId, accepted)`.
- Looks up current Redis presence for **both** users.
- Broadcasts `FriendRequestAccepted` to **both** users' personal channels, including `status` so the recipient immediately sees the correct presence for their new friend (not hardcoded offline).

#### `declineRequest(int $userId, int $requesterId): void`
- Acquires ordered friendship lock.
- Finds and deletes the pending request from `requesterId → userId`.
- Broadcasts `FriendRequestDeclined` to the requester's personal channel.

#### `removeFriend(int $userId, int $friendId): void`
- Acquires ordered friendship lock.
- Deletes both directional `accepted` rows.
- Throws `DomainException` if neither row existed.
- Broadcasts `FriendRemoved` to **both** users' personal channels.

#### `getFriends(int $userId): Collection`
Returns mapped collection of accepted friends, each with presence status from Redis.

#### `getIncomingRequests(int $userId): Collection`
Returns requests where `friend_id = userId` and `status = pending`.

#### `getOutgoingRequests(int $userId): Collection`
Returns requests where `user_id = userId` and `status = pending`.

#### `searchUsers(string $query, int $currentUserId, int $limit = 20): Collection`
- Performs `LIKE %query%` search on `username`.
- Excludes the current user.
- Calls `getRelationshipStatus` for each result to include `relationshipStatus`.

#### `setUserPresence(int $userId, UserPresenceStatus $status): void`
- Sets `social:presence:user:{userId}` = status value with a status-specific TTL.
- Broadcasts `FriendStatusChanged` to all friends' personal channels.

#### `getUserPresence(int $userId): string`
- Returns the status string from Redis, or `offline` if the key is absent.

#### `heartbeat(int $userId): void`
- If the current status is `online`: silently extends TTL to 300s (no broadcast — friends already know you're online).
- For any other state (`away`, `in-game`, no key): calls `setUserPresence(Online)`, transitioning to online and notifying friends.
- This means the heartbeat is safe to call when returning from away or after a match ends — it will correctly broadcast the `online` transition in those cases.

#### `setAway(int $userId): void`
- If the current status is `in-game`: no-op (in-game status is not overridable by tab-hide).
- Otherwise: sets Redis key to `away` with TTL 90s and broadcasts `FriendStatusChanged` to all friends.
- Called when the browser tab is hidden.

### Private Methods

#### `findExistingRelationship(int $userId, int $friendId): ?Friendship`
Queries `friendships` for any row matching either direction.

#### `getRelationshipStatus(int $userId, int $otherUserId): string`
Returns a `RelationshipStatus` value string based on the current state:
- `none` — no row found
- `friends` — row with `status=accepted`
- `request_sent` — pending row where `user_id = userId`
- `request_received` — pending row where `user_id = otherUserId`

#### `broadcastStatusToFriends(int $userId, string $status): void`
Fetches all accepted friends of `userId` and broadcasts `FriendStatusChanged` to each of their personal channels.

#### `lockFriendship(int $userId1, int $userId2): array`
Returns two locks, always acquired in ascending user ID order to prevent deadlocks between two concurrent symmetric operations (e.g. both users removing each other simultaneously).

---

## Redis Keys

| Key Pattern                          | Type   | TTL    | Purpose                                                                      |
|--------------------------------------|--------|--------|------------------------------------------------------------------------------|
| `social:presence:user:{userId}`      | String | 300s / 90s / 3600s | User's current presence status (`online`, `away`, `in-game`, or absent = `offline`) |
| `social:lock:friendship:{userId}`    | String | 5000ms | Per-user mutex for friendship mutations                                      |

TTL varies by status: `online` → 300s, `away` → 90s, `in-game` → 3600s.

Locks use the same `SET key value PX ttlMs NX` + Lua CAS release pattern as the party system.

---

## Domain Events & Listeners

Events are fired for cross-domain side effects and registered in `AppServiceProvider`.

### `App\Auth\Events\UserLoggedOut`
Fired in `AuthManager::logout()` before `Auth::logout()`. Carries the authenticated `User` model.

**Listener: `SetUserOfflineOnLogoutListener`**
Calls `FriendshipManager::setUserPresence(Offline)`, which deletes presence from Redis and broadcasts `FriendStatusChanged` to all friends immediately on logout (rather than waiting for the 5-minute TTL to expire).

### `App\MatchMaking\Events\MatchStarted`
Fired at the end of `MatchMakingManager::startMatch()`. Carries `array $playerUserIds` and `int $matchId`.

**Listener: `SetUsersInGameOnMatchStartListener`**
Calls `FriendshipManager::setUserPresence(InGame)` for each player, setting a 3600s TTL. Friends will see the swords icon for these players and the "Invite to Party" action will be disabled.

---

## Broadcasting Events

All events are broadcast on the recipient's personal private channel `user.{hashedId}`.

### `FriendRequestReceived` — channel: `user.{recipientHash}`
Event name: `friend.request.received`

Sent to the recipient when a friend request is sent to them.
```json
{
  "requesterHash": "<hashedId>",
  "username": "Alice"
}
```

### `FriendRequestAccepted` — channel: `user.{recipientHash}`
Event name: `friend.request.accepted`

Sent to **both** the accepter and the original requester when a request is accepted.
```json
{
  "userHash": "<newFriendHashedId>",
  "username": "Bob",
  "friendsSince": "2026-01-15T10:00:00.000Z",
  "status": "online"
}
```
Each recipient receives their own copy where `userHash` and `status` belong to the *other* person. The `status` field reflects the friend's actual current presence so the frontend doesn't show them as offline immediately after accept.

### `FriendRequestDeclined` — channel: `user.{requesterHash}`
Event name: `friend.request.declined`

Sent only to the original requester.
```json
{
  "declinerHash": "<hashedId>"
}
```

### `FriendRemoved` — channel: `user.{recipientHash}`
Event name: `friend.removed`

Sent to **both** users when a friendship is removed.
```json
{
  "friendHash": "<removedFriendHashedId>"
}
```
Each user receives their own copy where `friendHash` is the *other* person.

### `FriendStatusChanged` — channel: `user.{recipientHash}`
Event name: `friend.status.changed`

Sent to all of a user's friends whenever that user's presence changes.
```json
{
  "friendHash": "<hashedId of user whose status changed>",
  "status": "online"
}
```
Possible `status` values: `online`, `offline`, `away`, `in-game`.

---

## Presence & Heartbeat System

Presence is maintained through Redis TTL with the following states and transitions:

### States

| Status    | TTL    | Set by                                               | UI                             |
|-----------|--------|------------------------------------------------------|--------------------------------|
| `online`  | 300s   | Heartbeat (initial or transition from away/in-game)  | Green pulsing dot              |
| `offline` | —      | No key in Redis (TTL expired or explicit logout)     | Grey dot                       |
| `away`    | 90s    | `POST /api/friends/away` (tab hide)                  | Amber dot                      |
| `in-game` | 3600s  | `MatchStarted` event listener when match begins      | Swords icon                    |

### Heartbeat Flow

1. When a user logs in or the frontend mounts, `useFriendsStore.startHeartbeat()` is called.
2. An initial `POST /api/friends/heartbeat` fires immediately.
3. A `setInterval` repeats every **120 seconds**.
4. If the user is currently `online`: extends TTL to 300s silently (no broadcast).
5. If the user is `away`, `in-game`, or has no key: sets `online` and broadcasts to friends.
6. `startHeartbeat()` always clears any existing interval before starting, so it is safe to call as a "restart" (e.g. when returning from away or after a match ends).

### Away Flow

1. `visibilitychange` fires when the tab is hidden.
2. Frontend calls `goAway()`: stops the heartbeat interval and calls `POST /api/friends/away`.
3. Backend `setAway()`: no-op if in-game; otherwise sets `away` with 90s TTL and broadcasts.
4. When the tab becomes visible again (and the user is not in a match), `startHeartbeat()` is called — the first heartbeat transitions back to `online`.
5. If the user never returns, the 90s TTL expires and presence falls to `offline` naturally.

### Logout Flow

1. `POST /api/auth/logout` → `AuthManager::logout()` fires `UserLoggedOut` event.
2. `SetUserOfflineOnLogoutListener` calls `setUserPresence(Offline)`.
3. Redis key is set to `offline` (or effectively cleared) and `FriendStatusChanged` is broadcast to all friends immediately — no waiting for TTL expiry.

### In-Game Flow

1. Match starts → `MatchMakingManager::startMatch()` fires `MatchStarted` event.
2. `SetUsersInGameOnMatchStartListener` calls `setUserPresence(InGame)` for each player (TTL: 3600s).
3. Frontend stops the heartbeat when `partyState` transitions to `InMatch` — this prevents the 2-minute heartbeat from overriding the in-game status mid-match.
4. Backend `setAway()` also guards against overriding in-game status.
5. When the party leaves `InMatch` state, `startHeartbeat()` restarts — the next heartbeat transitions the user back to `online`.

---

## Frontend

### API Client — `frontend/api/friends.js`

| Function                            | HTTP | Endpoint                         |
|-------------------------------------|------|----------------------------------|
| `getFriends()`                      | GET  | `api/friends`                    |
| `getFriendRequests()`               | GET  | `api/friends/requests`           |
| `searchUsers(query)`                | GET  | `api/friends/search?query=...`   |
| `sendFriendRequest(friendHash)`     | POST | `api/friends/request`            |
| `acceptFriendRequest(requesterHash)`| POST | `api/friends/request/accept`     |
| `declineFriendRequest(requesterHash)`| POST| `api/friends/request/decline`    |
| `removeFriend(friendHash)`          | POST | `api/friends/remove`             |
| `sendHeartbeat()`                   | POST | `api/friends/heartbeat`          |
| `sendAway()`                        | POST | `api/friends/away`               |

### Zustand Store — `frontend/store/friends-store.js`

```js
{
  friends: [],           // [{ userHash, username, status, friendsSince }]
  incomingRequests: [],  // [{ requesterHash, username, requestedAt }]
  outgoingRequests: [],  // [{ recipientHash, username, requestedAt }]
  isLoading: true,
  heartbeatInterval: null,
}
```

**Actions:**
- `sendRequest(friendHash)` — sends request, refreshes outgoing list
- `acceptRequest(requesterHash)` — accepts, refreshes friends + requests
- `declineRequest(requesterHash)` — declines, removes from local incoming list
- `removeFriend(friendHash)` — removes, updates local friends list
- `searchForUsers(query)` — returns results array
- `refreshFriends()` — fetches and sets `friends`
- `refreshRequests()` — fetches and sets `incomingRequests` + `outgoingRequests`
- `restoreState()` — calls both refresh methods in parallel on mount
- `startHeartbeat()` — clears any existing interval, sends immediate heartbeat, starts 120s interval; safe to call as a restart
- `stopHeartbeat()` — clears the interval
- `goAway()` — calls `stopHeartbeat()` then `sendAway()` (tab-hide handler)

**WebSocket handlers:**
- `handleRequestReceived(data)` — appends to `incomingRequests`, shows toast
- `handleRequestAccepted(data)` — removes from `outgoingRequests`, appends to `friends` using `data.status` for presence (not hardcoded offline), shows toast
- `handleRequestDeclined(data)` — removes from `outgoingRequests` by `declinerHash`
- `handleFriendRemoved(data)` — removes from `friends` by `friendHash`
- `handleStatusChanged(data)` — updates matching friend's `status` field

### Frontend Constants — `frontend/lib/constants/social.js`

```js
export const FriendshipStatus = { Pending, Accepted, Blocked }
export const UserPresenceStatus = { Online: 'online', Offline: 'offline', Away: 'away', InGame: 'in-game' }
export const RelationshipStatus = { None, Friends, RequestSent, RequestReceived }
export const FriendAction = { RequestSent, RequestAccepted, RequestDeclined, FriendRemoved, StatusChanged }
```

### Component — `frontend/components/social/friends-sidebar.jsx`

Renders the collapsible friends sidebar showing:
- Friends with presence indicator (green dot = online, amber dot = away, swords icon = in-game, grey dot = offline)
- Pending incoming request count badge
- Accept/decline controls for incoming requests
- "Add Friend" flow via user search
- "Invite to party" button per friend — **disabled** when `friend.status === 'in-game'`

### Component — `frontend/components/layout/social-layout.jsx`

Manages all real-time presence side effects:
- On mount: `restoreState()`, `restoreFriendsState()`, `startHeartbeat()`
- `visibilitychange` listener: `hidden` → `goAway()`, `visible` (not in match) → `startHeartbeat()`
- `partyState` watcher: transitions **into** `InMatch` → `stopHeartbeat()`; transitions **out of** `InMatch` → `startHeartbeat()`

---

## Workflow Walkthroughs

### Sending and accepting a friend request

```
Alice → POST /api/friends/request { friendHash: bobHash }
  FriendshipManager::sendRequest
    → Acquires locks for [min(aliceId,bobId), max(aliceId,bobId)]
    → No existing relationship found
    → Creates Friendship(user_id=Alice, friend_id=Bob, status=pending)
    → Broadcasts FriendRequestReceived → user.{bobHash}
      { requesterHash: aliceHash, username: "Alice" }

Bob → receives WebSocket event friend.request.received
  → useFriendsStore.handleRequestReceived({ requesterHash: aliceHash, username: "Alice" })
  → Toast: "Alice sent you a friend request"

Bob → POST /api/friends/request/accept { requesterHash: aliceHash }
  FriendshipManager::acceptRequest
    → Acquires locks
    → Finds pending row (user_id=Alice, friend_id=Bob, status=pending) → accepted
    → Creates reverse row (user_id=Bob, friend_id=Alice, status=accepted)
    → Looks up Alice's presence (e.g. "online") and Bob's presence (e.g. "away")
    → Broadcasts FriendRequestAccepted → user.{bobHash}
        { userHash: aliceHash, username: "Alice", friendsSince: "...", status: "online" }
    → Broadcasts FriendRequestAccepted → user.{aliceHash}
        { userHash: bobHash, username: "Bob", friendsSince: "...", status: "away" }

Both clients → handleRequestAccepted
  → Remove from outgoingRequests / incomingRequests
  → Append to friends list with actual presence status
```

### Removing a friend

```
Alice → POST /api/friends/remove { friendHash: bobHash }
  FriendshipManager::removeFriend
    → Acquires ordered locks
    → Deletes (Alice→Bob, accepted) and (Bob→Alice, accepted)
    → Broadcasts FriendRemoved → user.{aliceHash} { friendHash: bobHash }
    → Broadcasts FriendRemoved → user.{bobHash} { friendHash: aliceHash }

Both clients → handleFriendRemoved
  → Remove matching entry from friends array
```

### Presence heartbeat flow

```
User opens app
  → useFriendsStore.startHeartbeat()
  → POST /api/friends/heartbeat (immediately)
      FriendshipManager::heartbeat
        → social:presence:user:{userId} doesn't exist (or is 'away'/'in-game')
        → setUserPresence(Online)
          → SET social:presence:user:{userId} "online" EX 300
          → broadcastStatusToFriends → FriendStatusChanged to all friends

Every 120s
  → POST /api/friends/heartbeat
      → Status is 'online' → EXPIRE social:presence:user:{userId} 300 (silent, no broadcast)

User hides tab
  → visibilitychange (hidden) → goAway()
      → stopHeartbeat() (interval cleared)
      → POST /api/friends/away
          FriendshipManager::setAway
            → SET social:presence:user:{userId} "away" EX 90
            → broadcastStatusToFriends → FriendStatusChanged "away" to all friends

User returns to tab (not in match)
  → visibilitychange (visible) → startHeartbeat()
      → POST /api/friends/heartbeat immediately
          → Status is 'away' → setUserPresence(Online) → broadcast "online"
      → 120s interval restarted

User closes tab entirely
  → After 90s, 'away' key expires → friends see "offline" on next getFriends() call
```

### Logout goes offline immediately

```
User → POST /api/auth/logout
  AuthManager::logout()
    → event(new UserLoggedOut($user))
        SetUserOfflineOnLogoutListener::handle
          → FriendshipManager::setUserPresence(Offline)
              → SET social:presence:user:{userId} "offline" EX 300
              → broadcastStatusToFriends → FriendStatusChanged "offline" immediately
    → Auth::logout()
```

### In-game status from matchmaking

```
Match confirmed for [Alice, Bob, Charlie, Dana]
  MatchMakingManager::startMatch()
    → Creates GameMatch record
    → Broadcasts MMMatchStarted to each player's personal channel
    → event(new MatchStarted([aliceId, bobId, charlieId, danaId], matchId))
        SetUsersInGameOnMatchStartListener::handle
          → For each userId:
              FriendshipManager::setUserPresence(InGame)
                → SET social:presence:user:{userId} "in-game" EX 3600
                → broadcastStatusToFriends → FriendStatusChanged "in-game" to their friends

Friends see swords icon for all four players.
"Invite to Party" is disabled in the friends sidebar for in-game players.

Frontend (each player):
  → partyState transitions to GroupStatus.InMatch
  → stopHeartbeat() — prevents 2-minute heartbeat from overriding in-game status

Match ends / player returns to lobby:
  → partyState transitions out of InMatch
  → startHeartbeat() — first heartbeat sees 'in-game' key, calls setUserPresence(Online)
  → Friends see player back as "online"
```

### User search with relationship context

```
Alice → GET /api/friends/search?query=bob
  FriendshipManager::searchUsers("bob", aliceId, 20)
    → Users LIKE '%bob%' WHERE id != aliceId
    → For each result, getRelationshipStatus(aliceId, userId):
        - No row found → "none"
        - Pending, user_id=aliceId → "request_sent"
        - Pending, user_id=userId → "request_received"
        - Accepted → "friends"
    → Returns UserSearchResource
      { results: [{ userHash, username, relationshipStatus }] }
```
