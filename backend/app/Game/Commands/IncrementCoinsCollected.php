<?php

declare(strict_types=1);

namespace App\Game\Commands;

use App\Game\Context\TurnContext;
use App\Game\Data\GameDataItem;

class IncrementCoinsCollected implements Command
{
    public function __construct(
        public int $teamId,
        public string $triggeredBy,
    ) {}

    public function execute(TurnContext $turnContext): void
    {
        $coins = $turnContext->getGameData()->array(GameDataItem::CoinsCollected->value);
        $coins[$this->teamId] = ($coins[$this->teamId] ?? 0) + 1;
        $turnContext->updateGameData($turnContext->getGameData()->set(GameDataItem::CoinsCollected->value, $coins));
    }
}
