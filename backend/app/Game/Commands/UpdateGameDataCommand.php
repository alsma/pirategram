<?php

declare(strict_types=1);

namespace App\Game\Commands;

use App\Exceptions\RuntimeException;
use App\Game\Context\TurnContext;

readonly class UpdateGameDataCommand implements Command
{
    const string ACTION_SET = 'set';

    const string ACTION_UNSET = 'unset';

    const string ACTION_INCREMENT = 'increment';

    const string ACTION_DECREMENT = 'decrement';

    public function __construct(
        public string $action,
        public string $dataItem,
        public mixed $payload,
        public string $triggeredBy,
    ) {}

    public static function set(string $dataItem, mixed $payload, string $triggeredBy): self
    {
        return new self(self::ACTION_SET, $dataItem, $payload, $triggeredBy);
    }

    public static function unset(string $dataItem, string $triggeredBy): self
    {
        return new self(self::ACTION_UNSET, $dataItem, null, $triggeredBy);
    }

    public static function increment(string $dataItem, string $triggeredBy): self
    {
        return new self(self::ACTION_INCREMENT, $dataItem, null, $triggeredBy);
    }

    public static function decrement(string $dataItem, string $triggeredBy): self
    {
        return new self(self::ACTION_DECREMENT, $dataItem, null, $triggeredBy);
    }

    public function execute(TurnContext $turnContext): void
    {
        $gameData = $turnContext->getGameData();

        switch ($this->action) {
            case self::ACTION_SET:
                $updatedGameData = $gameData->set($this->dataItem, $this->payload);
                break;
            case self::ACTION_UNSET:
                $updatedGameData = $gameData->unset($this->dataItem);
                break;
            case self::ACTION_INCREMENT:
                $updatedGameData = $gameData->increment($this->dataItem);
                break;
            case self::ACTION_DECREMENT:
                $updatedGameData = $gameData->decrement($this->dataItem);
                break;
            default:
                throw new RuntimeException('Unknown action');
        }

        $turnContext->updateGameData($updatedGameData);
    }
}
