<?php

declare(strict_types=1);

namespace App\Game\Http\Resources;

use App\Game\Models\GameState;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property GameState $resource */
class GameStateResource extends JsonResource
{
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

    public function toArray($request): array
    {
        return [
            'hash' => $this->resource->getHashedId(),
            'type' => $this->resource->type->value,
            'board' => GameBoardResource::make($this->resource->board),
            'players' => GamePlayerResource::collection($this->resource->players),
            $this->mergeWhen($this->resource->current_turn_game_player_id, fn () => [
                'currentTurnPlayer' => GamePlayerResource::make($this->resource->currentTurn),
            ]),
        ];
    }
}
