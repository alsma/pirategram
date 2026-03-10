# Party System

The party system lets multiple players group up before entering the matchmaking queue together. A party is a temporary grouping that exists in both the database (for persistence and membership) and Redis (for the matchmaking engine). Parties are only available for modes that require multiple players per team — currently **2v2**.

---

## Directory Structure

```
backend/app/MatchMaking/
├── PartyManager.php                          # Core service
├── Models/
│   ├── Party.php
│   └── PartyMember.php
├── Http/
│   ├── Controllers/
│   │   ├── GetPartyController.php
│   │   ├── JoinPartyController.php
│   │   ├── LeavePartyController.php
│   │   ├── DisbandPartyController.php
│   │   ├── CreatePartyInviteController.php
│   │   ├── AcceptPartyInviteController.php
│   │   ├── DeclinePartyInviteController.php
│   │   ├── KickPartyMemberController.php
│   │   ├── PromotePartyMemberController.php
│   │   ├── StartPartySearchController.php
│   │   └── CancelPartySearchController.php
│   ├── Requests/
│   │   ├── JoinPartyRequest.php
│   │   ├── LeavePartyRequest.php
│   │   ├── CreatePartyInviteRequest.php
│   │   ├── AcceptPartyInviteRequest.php
│   │   ├── DeclinePartyInviteRequest.php
│   │   ├── KickPartyMemberRequest.php
│   │   ├── PromotePartyMemberRequest.php
│   │   └── StartPartySearchRequest.php
│   └── Resources/
│       └── PartyResource.php
├── Broadcasting/
│   ├── PartyUpdated.php
│   ├── UserPartyUpdated.php
│   ├── PartyInviteCreated.php
│   └── PartyInviteDeclined.php
└── ValueObjects/
    ├── PartyStatus.php
    └── PartyAction.php

frontend/
├── api/party.js
├── store/party-store.js
└── components/social/party-bar.jsx
```

---

## Database Schema

### `parties`

| Column      | Type         | Notes                                  |
|-------------|--------------|----------------------------------------|
| `id`        | bigint PK    |                                        |
| `leader_id` | bigint FK    | References `users.id`                  |
| `mode`      | string       | Game mode value (e.g. `2v2`)           |
| `status`    | string       | `idle` or `searching`                  |
| `created_at`| timestamp    |                                        |
| `updated_at`| timestamp    |                                        |

### `party_members`

| Column       | Type      | Notes                                 |
|--------------|-----------|---------------------------------------|
| `id`         | bigint PK |                                       |
| `user_id`    | bigint FK | References `users.id`                 |
| `party_id`   | bigint FK | References `parties.id`               |
| `created_at` | timestamp | No `updated_at` (`UPDATED_AT = null`) |

A user can only be in one party at a time. `createParty` deletes any pre-existing `party_members` row for the leader before creating a new party.

---

## Enums / Value Objects

### `PartyStatus` (`backend/app/MatchMaking/ValueObjects/PartyStatus.php`)

| Case        | Value        |
|-------------|--------------|
| `Idle`      | `idle`       |
| `Searching` | `searching`  |

### `PartyAction` (`backend/app/MatchMaking/ValueObjects/PartyAction.php`)

| Case            | Value           | When fired                                  |
|-----------------|-----------------|---------------------------------------------|
| `Created`       | `created`       | New party created                           |
| `Disbanded`     | `disbanded`     | Party deleted (leader disbanded or last member left) |
| `MemberJoined`  | `memberJoined`  | A user accepted an invite and joined        |
| `MemberLeft`    | `memberLeft`    | A member left voluntarily                   |
| `MemberKicked`  | `memberKicked`  | Leader kicked a member                      |
| `LeaderChanged` | `leaderChanged` | Leader left (auto-promote) or leader promoted a member |
| `ModeChanged`   | `modeChanged`   | Leader changed the game mode                |

### `GameMode` (shared with matchmaking)

Only modes in `PartyManager::MODES` are valid for parties:

```php
public const array MODES = [
    GameMode::TwoVsTwo->value => 4,  // '2v2' → max 4 players
];
```

---

## API Routes

All routes are under prefix `/api/mm` and require `auth:sanctum`.

| Method | Path                         | Controller                       | Description                          |
|--------|------------------------------|----------------------------------|--------------------------------------|
| GET    | `/api/mm/party`              | `GetPartyController`             | Get current user's party state       |
| POST   | `/api/mm/party/join`         | `JoinPartyController`            | Join a party by hash                 |
| POST   | `/api/mm/party/leave`        | `LeavePartyController`           | Leave current party                  |
| POST   | `/api/mm/party/disband`      | `DisbandPartyController`         | Disband party (leader only)          |
| POST   | `/api/mm/party/invite`       | `CreatePartyInviteController`    | Send invite to a user                |
| POST   | `/api/mm/party/invite/accept`| `AcceptPartyInviteController`    | Accept a pending invite              |
| POST   | `/api/mm/party/invite/decline`| `DeclinePartyInviteController`  | Decline a pending invite             |
| POST   | `/api/mm/party/kick`         | `KickPartyMemberController`      | Kick a member (leader only)          |
| POST   | `/api/mm/party/promote`      | `PromotePartyMemberController`   | Promote member to leader (leader only)|
| POST   | `/api/mm/party/search/start` | `StartPartySearchController`     | Start MM queue for the party         |
| POST   | `/api/mm/party/search/cancel`| `CancelPartySearchController`    | Cancel MM queue for the party        |

### Request / Response Shapes

All user/party identifiers sent from the frontend are **hashed IDs** (Hashids). The form request classes resolve them back to integer IDs before the controller uses them.

**GET `/api/mm/party`** — returns `PartyResource` or `null`
```json
{
  "partyHash": "abc123",
  "leaderId": 1,
  "leaderHash": "def456",
  "mode": "2v2",
  "members": [
    { "userId": 1, "userHash": "def456", "username": "Alice" },
    { "userId": 2, "userHash": "ghi789", "username": "Bob" }
  ],
  "maxPlayers": 4
}
```

**POST `/api/mm/party/invite`**
```json
{ "userId": "<userHash>", "mode": "2v2" }
```

**POST `/api/mm/party/invite/accept`**
```json
{ "leaderId": "<leaderHash>" }
```

**POST `/api/mm/party/invite/decline`**
```json
{ "leaderId": "<leaderHash>" }
```

**POST `/api/mm/party/kick`**
```json
{ "memberUserId": "<memberHash>" }
```

**POST `/api/mm/party/promote`**
```json
{ "newLeaderUserId": "<memberHash>" }
```

**POST `/api/mm/party/search/start`**
```json
{ "partyHash": "<partyHash>", "sessionId": "<tabSessionId>" }
```

---

## Service: `PartyManager`

Location: `backend/app/MatchMaking/PartyManager.php`

Injected dependencies: `MatchMakingManager`, `RedisManager`.

### Public Methods

#### `createParty(int $leaderId): Party`
- Removes any existing `party_members` row for the leader (ensures clean state).
- Creates a new `Party` with `mode=2v2`, `status=idle`.
- Adds the leader as the first `PartyMember`.
- Calls `syncRedisParty` to write the party hash to Redis.
- Broadcasts `PartyUpdated` with action `created`.

#### `disband(int $leaderId, Party $party): void`
- Acquires party lock.
- Verifies `$leaderId` is the leader (`assertLeader`).
- Cancels any active MM search (`cancelIfSearching`).
- Deletes all `party_members` rows and the `Party` record.
- Removes the Redis group key and queue entry.
- Broadcasts `PartyUpdated` with action `disbanded`.

#### `createInvite(int $leaderId, int $invitedUserId, string $mode, int $ttlSeconds = 120): void`
- Prevents self-invite.
- Acquires leader-level lock (`lockLeaderInvites`).
- **If leader already has a party:** Locks the party, verifies leadership, asserts not searching, sets Redis invite key and tracks leader in user's invite-leaders set, broadcasts `PartyInviteCreated` to the invited user's personal channel.
- **If no party yet:** Checks mode consistency via `mm:invite:leader:{leaderId}:mode`, then writes the invite key and broadcasts the invite.
- Silently ignores duplicate invites (same leader→user combination already pending).

#### `acceptInvite(int $userId, int $leaderId): Party`
- Acquires user lock to prevent concurrent accepts.
- Validates invite exists in Redis.
- Acquires leader lock.
- Creates the party if it doesn't exist yet (first invitee accepting triggers party creation).
- Calls `join($userId, $party)`.
- Clears all pending invites for the user (`clearUserInvites`).
- Clears the leader's mode-lock key.
- Returns the fresh party.

#### `declineInvite(int $userId, int $leaderId): void`
- Validates the invite key exists in Redis.
- Deletes the invite key and removes the leader from the user's invite-leaders set.
- Broadcasts `PartyInviteDeclined` to the leader's personal channel.

#### `join(int $userId, Party $party): void`
- Acquires party lock.
- Checks the user isn't already in any party.
- Checks party capacity (`ensureCapacity`).
- Creates the `PartyMember` row.
- Cancels search if the party was searching.
- Calls `syncRedisParty`.
- Broadcasts `PartyUpdated` on the party channel and `UserPartyUpdated` on every member's personal channel (action: `memberJoined`).

#### `leave(int $userId, Party $party): void`
- Acquires party lock.
- Deletes the member's row.
- **If no members remain:** Cancels search, deletes party, removes Redis key, broadcasts `disbanded` to party channel and `disbanded` to the leaving user's personal channel.
- **If members remain and user was leader:** Auto-promotes the oldest remaining member (ordered by `created_at`), broadcasts `leaderChanged`.
- **If members remain and user was not leader:** Broadcasts `memberLeft`.
- In all remaining-member cases: syncs Redis, broadcasts to each remaining member's personal channel, and sends `disbanded` action to the leaving user's personal channel (so their frontend clears party state).

#### `promote(int $leaderId, Party $party, int $newLeaderUserId): void`
- Acquires party lock.
- Asserts leadership.
- Verifies the target user is in the party.
- Updates `leader_id`, syncs Redis, broadcasts `leaderChanged`.

#### `kick(int $leaderId, Party $party, int $memberUserId): void`
- Acquires party lock.
- Asserts leadership; prevents leader kicking themselves.
- Deletes member row.
- **If only leader remains after kick:** Cancels search, disbands party, broadcasts `disbanded`.
- **Otherwise:** Cancels search, syncs Redis, broadcasts `memberKicked`.

#### `setMode(int $leaderId, Party $party, string $mode): void`
- Validates mode is in `MODES`.
- Acquires party lock, asserts leadership, asserts not searching.
- Checks current member count doesn't exceed the new mode's max.
- Updates `mode`, cancels search if needed, syncs Redis, broadcasts `modeChanged`.

#### `syncRedisParty(int $partyId): void`
Writes the full group hash to `mm:group:party:{partyId}` with these fields:

| Field          | Value                                    |
|----------------|------------------------------------------|
| `group_key`    | `party:{partyId}`                        |
| `party_id`     | integer party ID                         |
| `leader_id`    | integer leader user ID                   |
| `members_json` | JSON array of `{ id, mmr }` per member   |
| `size`         | number of members                        |
| `mmr`          | average MMR (rounded integer)            |
| `base_mmr`     | same as `mmr` at creation time           |
| `mode`         | game mode string                         |
| `status`       | `idle` initially                         |

#### `getUserParty(int $userId): ?Party`
Returns the `Party` the user belongs to, or `null`.

#### `ensureIsLeader(Party $party, int $userId): void`
Throws `DomainException` if the user is not the party leader.

---

## Redis Keys

| Key Pattern                                   | Type   | TTL      | Purpose                                              |
|-----------------------------------------------|--------|----------|------------------------------------------------------|
| `mm:group:party:{partyId}`                    | Hash   | None     | Party data for the MM engine (updated on membership change) |
| `mm:invite:user:{userId}:leader:{leaderId}`   | String | 120s     | Pending invite payload (JSON: `leader_id`, `mode`, optional `party_id`) |
| `mm:invite:leader:{leaderId}:mode`            | String | 120s     | Mode consistency lock when party doesn't exist yet   |
| `mm:invite:user:{userId}:leaders`             | Set    | 180s     | All leader IDs that have sent invites to this user   |
| `mm:lock:party:{partyId}`                     | String | 5000ms   | Mutex for party mutations                            |
| `mm:lock:user:{userId}`                       | String | 5000ms   | Mutex preventing concurrent invite accepts           |
| `mm:lock:leader:invites:{leaderId}`           | String | 5000ms   | Mutex for leader invite operations                   |

The party group key (`mm:group:party:{id}`) feeds directly into the `GroupAssembler` during matchmaking, which reads `mmr`, `size`, `mode`, and `members_json` to pair groups into teams.

Locks are implemented with `SET key value PX ttlMs NX` and released via a Lua CAS script to ensure only the lock holder can release.

---

## Broadcasting Events

### `PartyUpdated` — channel: `party.{partyHash}`
Event name: `party.updated`

Broadcast to the party's dedicated private channel. All party members who have subscribed to this channel receive it.

Payload:
```json
{
  "action": "<PartyAction value>",
  "state": { /* PartyResource shape, empty on disbanded */ }
}
```

### `UserPartyUpdated` — channel: `user.{userHash}`
Event name: `party.updated`

Same payload shape as `PartyUpdated` but broadcast on each member's personal channel. Used so members receive party updates even if they haven't yet subscribed to the `party.*` channel. The leaving user receives a `disbanded` action on their personal channel to clear their party state.

### `PartyInviteCreated` — channel: `user.{invitedUserHash}`
Event name: `party.invite.created`

Payload:
```json
{
  "leaderHash": "<leaderHashedId>",
  "leaderUsername": "Alice",
  "mode": "2v2",
  "partyHash": "<partyHashedId or null>"
}
```

`partyHash` is `null` if the leader hasn't formed a party yet (the party will be created when the first invitee accepts).

### `PartyInviteDeclined` — channel: `user.{leaderHash}`
Event name: `party.invite.declined`

Payload:
```json
{
  "userHash": "<declinerHashedId>",
  "username": "Bob"
}
```

---

## Frontend

### API Client — `frontend/api/party.js`

| Function              | HTTP              | Endpoint                          |
|-----------------------|-------------------|-----------------------------------|
| `getPartyState()`     | GET               | `api/mm/party`                    |
| `createPartyInvite(userHash, mode)` | POST | `api/mm/party/invite`         |
| `acceptPartyInvite(leaderHash)` | POST    | `api/mm/party/invite/accept`  |
| `declinePartyInvite(leaderHash)` | POST   | `api/mm/party/invite/decline` |
| `leaveParty()`        | POST              | `api/mm/party/leave`              |
| `disbandParty()`      | POST              | `api/mm/party/disband`            |
| `kickPartyMember(memberHash)` | POST    | `api/mm/party/kick`           |
| `promotePartyMember(memberHash)` | POST | `api/mm/party/promote`        |
| `startPartySearch(partyHash)` | POST    | `api/mm/party/search/start`   |
| `cancelPartySearch()` | POST              | `api/mm/party/search/cancel`      |

`startPartySearch` and `cancelPartySearch` both attach the current tab `sessionId` from `@/api/matchmaking.js`.

### Zustand Store — `frontend/store/party-store.js`

The party store manages both party state and matchmaking state for the current user. Key state fields:

```js
{
  party: null,          // PartyResource shape or null
  pendingInvites: [],   // [{ leaderHash, leaderUsername, mode, partyHash? }]
  isLoadingParty: true,

  // Matchmaking state
  state: GroupStatus.Idle,
  mode: null,
  searchStartedAt: null,
  searchExpiresAt: null,
  ticketId: null,
  readyExpiresAt: null,
  startAt: null,
  slots: [],
  yourSlot: null,
  matchId: null,
}
```

**Party actions:** `sendInvite`, `acceptInvite`, `declineInvite`, `leaveParty`, `disbandParty`, `kickMember`, `promoteMember`

**Matchmaking actions:** `startQueue` (uses party search if in party, solo search otherwise), `cancelQueue`, `acceptMatch`, `declineMatch`

**WebSocket handlers:**
- `handlePartyUpdated(data)` — updates `party` state or clears it on `disbanded`
- `handleInviteReceived(data)` — appends to `pendingInvites`, shows toast
- `handleInviteDeclined(data)` — shows toast to leader

**State restoration:** `restoreState()` fetches both `getPartyState()` and `getState()` in parallel on mount, rebuilding complete client state from the server.

**Selectors:**
- `selectQueueStatus(state)` — returns `'queuing'`, `'lobby-ready'`, or `'idle'`
- `selectIsReady(state)` — returns whether the current user's slot is accepted

### Component — `frontend/components/social/party-bar.jsx`

Renders the persistent party bar showing current members, their status, leader controls (kick, promote, start search), and pending invite notifications.

---

## Workflow Walkthroughs

### Creating a party via invite (no party exists yet)

```
Alice (leader) → POST /api/mm/party/invite { userId: bobHash, mode: "2v2" }
  PartyManager::createInvite
    → Sets mm:invite:leader:{aliceId}:mode = "2v2" (TTL 120s)
    → Sets mm:invite:user:{bobId}:leader:{aliceId} = { leader_id, mode } (TTL 120s)
    → Adds aliceId to mm:invite:user:{bobId}:leaders
    → Broadcasts PartyInviteCreated → user.{bobHash}

Bob → POST /api/mm/party/invite/accept { leaderId: aliceHash }
  PartyManager::acceptInvite
    → Acquires user lock for Bob
    → Reads mm:invite:user:{bobId}:leader:{aliceId} → valid
    → Acquires leader lock for Alice
    → No party exists → PartyManager::createParty(aliceId)
        → Creates Party(mode=2v2, status=idle, leader=Alice)
        → Creates PartyMember(Alice)
        → Broadcasts PartyUpdated(created) → party.{partyHash}
    → PartyManager::join(bobId, party)
        → Creates PartyMember(Bob)
        → Broadcasts PartyUpdated(memberJoined) → party.{partyHash}
        → Broadcasts UserPartyUpdated(memberJoined) → user.{aliceHash}, user.{bobHash}
    → Clears all pending invites for Bob
    → Clears mm:invite:leader:{aliceId}:mode
```

### Starting party matchmaking

```
Alice (leader) → POST /api/mm/party/search/start { partyHash, sessionId }
  StartPartySearchController
    → Validates Alice is leader of the party
    → Delegates to MatchMakingManager (party group enters queue)
    → MM engine reads mm:group:party:{id} hash
    → GroupAssembler pairs party with compatible groups
    → Broadcasts ticket events to all party members' personal channels
```

### Member leaving (leader auto-promote)

```
Alice (leader) → POST /api/mm/party/leave
  PartyManager::leave
    → Deletes Alice's PartyMember row
    → Remaining members: [Bob]
    → wasLeader = true → party.leader_id = Bob (first by created_at)
    → Cancels search if active
    → Syncs Redis with new leader
    → Broadcasts PartyUpdated(leaderChanged) → party.{partyHash}
    → Broadcasts UserPartyUpdated(leaderChanged) → user.{bobHash}
    → Broadcasts UserPartyUpdated(disbanded) → user.{aliceHash}
      (Alice's client clears its party state)
```

### Kicking a member (party auto-disbands when only leader remains)

```
Alice (leader) kicks Bob (only other member)
  PartyManager::kick
    → Deletes Bob's PartyMember row
    → remainingMembers = 1 (only Alice)
    → Cancels search, deletes party record, removes Redis key
    → Broadcasts PartyUpdated(disbanded) → party.{partyHash}
```
