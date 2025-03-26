<?php

declare(strict_types=1);

namespace App\Game\Http\Requests;

use App\Game\Data\CellPosition;
use App\Game\Models\GameState;
use App\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class MakeTurnRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'gameHash' => 'required|string',
            'entityId' => 'required|string',
            'col' => 'nullable|numeric',
            'row' => 'nullable|numeric',
        ];
    }

    public function getGame(): GameState
    {
        return GameState::query()->ofHashedId($this->input('gameHash'))->firstOrFail();
    }

    public function user($guard = null): User
    {
        // TODO fix it
        return $this->getGame()->currentTurn->user;
    }

    public function getEntityId(): string
    {
        return $this->str('entityId')->toString();
    }

    public function getPosition(): CellPosition
    {
        return new CellPosition($this->integer('col'), $this->integer('row'));
    }
}
