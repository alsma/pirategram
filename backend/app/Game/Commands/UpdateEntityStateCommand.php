<?php

declare(strict_types=1);

namespace App\Game\Commands;

use App\Exceptions\RuntimeException;
use App\Game\Context\TurnContext;

readonly class UpdateEntityStateCommand implements Command
{
    const string ACTION_SET = 'set';

    const string ACTION_UNSET = 'unset';

    const string ACTION_INCREMENT = 'increment';

    const string ACTION_DECREMENT = 'decrement';

    public function __construct(
        public string $entityId,
        public string $action,
        public string $stateItem,
        public mixed $payload,
        public string $triggeredBy,
    ) {}

    public static function set(string $entityId, string $stateItem, mixed $payload, string $triggeredBy): self
    {
        return new self($entityId, self::ACTION_SET, $stateItem, $payload, $triggeredBy);
    }

    public static function unset(string $entityId, string $stateItem, string $triggeredBy): self
    {
        return new self($entityId, self::ACTION_UNSET, $stateItem, null, $triggeredBy);
    }

    public static function increment(string $entityId, string $stateItem, string $triggeredBy): self
    {
        return new self($entityId, self::ACTION_INCREMENT, $stateItem, null, $triggeredBy);
    }

    public static function decrement(string $entityId, string $stateItem, string $triggeredBy): self
    {
        return new self($entityId, self::ACTION_DECREMENT, $stateItem, null, $triggeredBy);
    }

    public function execute(TurnContext $turnContext): void
    {
        $entity = $turnContext->getEntities()->getEntityByIdOrFail($this->entityId);

        switch ($this->action) {
            case self::ACTION_SET:
                $updatedEntity = $entity->updateState->set($this->stateItem, $this->payload);
                break;
            case self::ACTION_UNSET:
                $updatedEntity = $entity->updateState->unset($this->stateItem);
                break;
            case self::ACTION_INCREMENT:
                $updatedEntity = $entity->updateState->increment($this->stateItem);
                break;
            case self::ACTION_DECREMENT:
                $updatedEntity = $entity->updateState->decrement($this->stateItem);
                break;
            default:
                throw new RuntimeException('Unknown action');
        }

        $turnContext->updateEntity($updatedEntity);
    }
}
