<?php

declare(strict_types=1);

namespace App\Game\Support;

use App\Exceptions\RuntimeException;
use App\Game\Data\GameType;
use App\Game\GameTypes\GameTypeManager;

trait GameTypeManagersAwareTrait
{
    private array $gameTypesManagers = [];

    private ?\Closure $gameTypeManagerResolver = null;

    public function registerGameTypeManager(GameType $gameType, string $className): void
    {
        $this->gameTypesManagers[$gameType->value] = $className;
    }

    public function setGameTypeManagerResolver(\Closure $resolver): void
    {
        $this->gameTypeManagerResolver = $resolver;
    }

    public function getGameTypeManager(GameType $gameType): GameTypeManager
    {
        if (!$this->gameTypeManagerResolver) {
            throw new RuntimeException('No game type manager resolver available.');
        }

        $managerClassName = $this->gameTypesManagers[$gameType->value] ?? throw new RuntimeException('No game type manager available.');

        return call_user_func($this->gameTypeManagerResolver, $managerClassName);
    }
}
