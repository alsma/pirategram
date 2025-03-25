<?php

declare(strict_types=1);

namespace App\Game;

use App\Exceptions\LocalizedException;
use App\Game\Data\CellPosition;
use App\Game\Data\GameBoard;
use App\Game\Data\GameType;
use App\Game\Models\GamePlayer;
use App\Game\Models\GameState;
use App\Game\Support\GameTypeManagersAwareTrait;
use App\User\Models\User;
use Illuminate\Support\Collection;

class GameManager
{
    use GameTypeManagersAwareTrait;

    public function getActiveGame(): GameState
    {
        return transaction(function () {
            $game = GameState::query()
                ->lockForUpdate()
                ->first();
            if (!$game) {
                $game = $this->newGame();
            }

            return $game;
        });
    }

    public function newGame(): GameState
    {
        $gameType = GameType::Classic;

        return transaction(function () use ($gameType) {
            $gameTypeManager = $this->getGameTypeManager($gameType);

            $game = new GameState;
            $game->type = $gameType;
            $game->board = $gameTypeManager->generateBoard();
            $game->entities = collect();
            $game->save();

            $players = collect();
            foreach (range(0, 3) as $item) {
                $user = User::factory()->create();
                $gamePlayer = new GamePlayer;
                $gamePlayer->user()->associate($user);
                $gamePlayer->gameState()->associate($game);
                $gamePlayer->order = $item;
                $gamePlayer->team_id = $item % 2;
                $gamePlayer->save();

                $players->push($gamePlayer);
            }

            $game->setRelation('players', $players);

            $game->entities = $gameTypeManager->generateEntities($players);

            $game->currentTurn()->associate($players->first());
            $game->save();

            return $game;
        });
    }

    public function makeTurn(GameState $game, User $user, CellPosition $position): GameState
    {
        return transaction(function () use ($game, $user, $position) {
            $game = GameState::query()->lockForUpdate()->findOrFail($game->id);
            // TODO check that user belongs to game
            if ($game->currentTurn->user->isNot($user)) {
                throw new LocalizedException('game_another_user_turn');
            }

            /** @var GameBoard $gameBoard */
            $gameBoard = $game->board;
            if (!$gameBoard->hasCell($position)) {
                throw new LocalizedException('game_invalid_turn_position');
            }

            $updatedCell = $gameBoard->getCell($position)->reveal();
            $gameBoard->setCell($position, $updatedCell);

            $nextTurn = $game->players
                ->sortBy('order', SORT_NUMERIC)
                ->firstWhere('order', '>', $game->currentTurn->order);
            if (!$nextTurn) {
                $nextTurn = $game->players->firstWhere('order', 0);
            }

            $game->currentTurn()->associate($nextTurn);
            $game->save();

            return $game;
        });
    }

    public function getAllowedTurnsForGameEntities(GameState $gameState): Collection
    {
        $entities = $gameState->entities->where('gamePlayerId', $gameState->current_turn_game_player_id);
        $gameTypeManager = $this->getGameTypeManager($gameState->type);

        return $gameTypeManager->getAllowedTurnsForEntities($gameState->board, $entities);
    }
}
