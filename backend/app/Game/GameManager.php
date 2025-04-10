<?php

declare(strict_types=1);

namespace App\Game;

use App\Exceptions\LocalizedException;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;
use App\Game\Data\EntityTurn;
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

    public function makeTurn(GameState $gameState, User $user, string $entityId, CellPosition $position, array $params): GameState
    {
        return transaction(function () use ($gameState, $user, $entityId, $position, $params) {
            $gameState = GameState::query()->lockForUpdate()->findOrFail($gameState->id);
            // TODO check that user belongs to game
            if ($gameState->currentTurn->user->isNot($user)) {
                throw new LocalizedException('game_another_user_turn');
            }

            /** @var Entity $entity */
            $entity = $gameState->entities->firstWhere('id', $entityId);
            if (!$entity) {
                throw new LocalizedException('game_invalid_turn');
            }

            /** @var GameBoard $gameBoard */
            $gameBoard = $gameState->board;
            if (!$gameBoard->hasCell($position)) {
                throw new LocalizedException('game_invalid_turn_position');
            }

            $allowedTurns = $this->getAllowedTurnsForGameEntities($gameState);
            $hasAllowedTurn = $allowedTurns->where('entityId', $entityId)
                ->where(fn (EntityTurn $entityTurn) => $entityTurn->position->is($position))
                ->isNotEmpty();
            if (!$hasAllowedTurn) {
                throw new LocalizedException('game_invalid_turn_position');
            }

            $gameTypeManager = $this->getGameTypeManager($gameState->type);
            $gameTypeManager->processTurn($gameState, $entity, $position, $params);

            $gameState->save();

            return $gameState;
        });
    }

    public function getAllowedTurnsForGameEntities(GameState $gameState): Collection
    {
        $gameTypeManager = $this->getGameTypeManager($gameState->type);

        return $gameTypeManager->getAllowedTurns($gameState, $gameState->currentTurn);
    }
}
