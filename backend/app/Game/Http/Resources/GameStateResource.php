<?php

declare(strict_types=1);

namespace App\Game\Http\Resources;

use App\Game\GameManager;
use App\Game\Models\GameState;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property GameState $resource */
class GameStateResource extends JsonResource
{
    private bool $withAllowedTurns = false;

    public function __construct(GameState $resource)
    {
        $resource->load([
            'currentTurn',
            'currentTurn.user',
            'players',
            'players.user',
        ]);

        parent::__construct($resource);
    }

    public function withAllowedTurns(bool $value = true): self
    {
        $this->withAllowedTurns = $value;

        return $this;
    }

    public function toArray($request): array
    {
        return [
            'hash' => $this->resource->getHashedId(),
            'type' => $this->resource->type->value,
            'board' => GameBoardResource::make($this->resource->board),
            'entities' => EntityResource::collection($this->resource->entities),
            'players' => GamePlayerResource::collection($this->resource->players),
            'data' => $this->resource->data->toArray(),
            $this->mergeWhen($this->resource->current_turn_game_player_id, fn () => [
                'currentTurnPlayer' => GamePlayerResource::make($this->resource->currentTurn),
            ]),

            $this->mergeWhen($this->withAllowedTurns, function () {
                /** @var GameManager $manager */
                $manager = app(GameManager::class);

                return [
                    'allowedTurns' => EntityTurnResource::collection($manager->getAllowedTurnsForGameEntities($this->resource)),
                ];
            }),
        ];
    }
}
