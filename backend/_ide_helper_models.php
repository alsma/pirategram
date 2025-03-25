<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Game\Models{
/**
 * 
 *
 * @property int $id
 * @property int $game_state_id
 * @property int $user_id
 * @property int $team_id
 * @property int $order
 * @property-read \App\Game\Models\GameState|null $gameState
 * @property-read \App\User\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereGameStateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereUserId($value)
 */
	class GamePlayer extends \Eloquent {}
}

namespace App\Game\Models{
/**
 * 
 *
 * @property int $id
 * @property \App\Game\Data\GameType $type
 * @property \App\Game\Data\GameBoard $board
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $current_turn_game_player_id
 * @property-read \App\Game\Models\GamePlayer|null $currentTurn
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Game\Models\GamePlayer> $players
 * @property-read int|null $players_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\User\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameState newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameState newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameState query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameState whereBoard($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameState whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameState whereCurrentTurnGamePlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameState whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameState whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameState whereUpdatedAt($value)
 */
	class GameState extends \Eloquent {}
}

namespace App\User\Models{
/**
 * 
 *
 * @property int $id
 * @property string $username
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $api_token
 * @property string $language
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @method static \Database\Factories\User\Models\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User ofEmail(string $email)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User ofUsername(string $username)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereApiToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUsername($value)
 */
	class User extends \Eloquent {}
}

