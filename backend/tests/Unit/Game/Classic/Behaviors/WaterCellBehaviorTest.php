<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Classic\Behaviors;

use App\Game\Data\CellPosition;
use App\Game\Data\ContextData;
use App\Game\Data\Entity;
use App\Game\Data\EntityType;
use App\Game\Data\GameDataItem;
use App\Game\GameTypes\Classic\Behaviors\WaterCellBehavior;
use Tests\TestCase;

class WaterCellBehaviorTest extends TestCase
{
    use TurnContextBuilderTrait;

    private WaterCellBehavior $behavior;

    protected function setUp(): void
    {
        parent::setUp();

        $this->behavior = new WaterCellBehavior;
    }

    public function test_on_enter_with_own_ship_collects_coin(): void
    {
        $turnContext = $this->create2x2TurnContext();

        $pirateStartPosition = new CellPosition(2, 1);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(2, 0), $this->turnPlayerId);
        $coin = new Entity(EntityType::Coin, new CellPosition(2, 1));

        $turnContext->setTurnEntity($pirate);
        $turnContext->mergeEntities(collect([$pirate, $coin]));
        $turnContext->mergeData(new ContextData(['carriageEntity' => $coin]));

        $turnPosition = new CellPosition(2, 0);
        $turnCell = $turnContext->getCell($turnPosition);

        $this->assertSame(0, $turnContext->getGameData()->array(GameDataItem::CoinsCollected->value)[$this->turnPlayerTeamId] ?? 0);

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $turnCell, $turnPosition);

        $this->assertNull($turnContext->getEntities()->getEntityById($coin->id));
        $this->assertSame(1, $turnContext->getGameData()->array(GameDataItem::CoinsCollected->value)[$this->turnPlayerTeamId] ?? 0);
    }

    public function test_on_enter_with_teammate_ship_collects_coin(): void
    {
        $turnContext = $this->create2x2TurnContext();

        $pirateStartPosition = new CellPosition(2, 3);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(2, 4), $this->turnPlayerId);
        $coin = new Entity(EntityType::Coin, new CellPosition(2, 3));

        $turnContext->setTurnEntity($pirate);
        $turnContext->mergeEntities(collect([$pirate, $coin]));
        $turnContext->mergeData(new ContextData(['carriageEntity' => $coin]));

        $turnPosition = new CellPosition(2, 4);
        $turnCell = $turnContext->getCell($turnPosition);

        $this->assertSame(0, $turnContext->getGameData()->array(GameDataItem::CoinsCollected->value)[$this->turnPlayerTeamId] ?? 0);

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $turnCell, $turnPosition);

        $this->assertNull($turnContext->getEntities()->getEntityById($coin->id));
        $this->assertSame(1, $turnContext->getGameData()->array(GameDataItem::CoinsCollected->value)[$this->turnPlayerTeamId] ?? 0);
    }

    public function test_on_enter_with_enemy_ship_drops_coin_without_score(): void
    {
        $turnContext = $this->create2x2TurnContext();

        $pirateStartPosition = new CellPosition(1, 2);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(0, 2), $this->turnPlayerId);
        $coin = new Entity(EntityType::Coin, new CellPosition(1, 2));

        $turnContext->setTurnEntity($pirate);
        $turnContext->mergeEntities(collect([$pirate, $coin]));
        $turnContext->mergeData(new ContextData(['carriageEntity' => $coin]));

        $targetPosition = new CellPosition(0, 2);
        $targetCell = $turnContext->getCell($targetPosition);

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $targetCell, $targetPosition);

        $this->assertNull($turnContext->getEntities()->getEntityById($coin->id));
        $this->assertEmpty($turnContext->getGameData()->array(GameDataItem::CoinsCollected->value));
    }

    public function test_on_enter_without_ship_drops_coin_without_score(): void
    {
        $turnContext = $this->create2x2TurnContext();

        $pirateStartPosition = new CellPosition(1, 1);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(0, 1), $this->turnPlayerId);
        $coin = new Entity(EntityType::Coin, new CellPosition(1, 1));

        $turnContext->setTurnEntity($pirate);
        $turnContext->mergeEntities(collect([$pirate, $coin]));
        $turnContext->mergeData(new ContextData(['carriageEntity' => $coin]));

        $targetPosition = new CellPosition(0, 1);
        $targetCell = $turnContext->getCell($targetPosition);

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $targetCell, $targetPosition);

        $this->assertNull($turnContext->getEntities()->getEntityById($coin->id));
        $this->assertEmpty($turnContext->getGameData()->array(GameDataItem::CoinsCollected->value));
    }

    public function test_allows_entity_to_stay(): void
    {
        $this->assertTrue($this->behavior->allowsEntityToStay());
    }
}
