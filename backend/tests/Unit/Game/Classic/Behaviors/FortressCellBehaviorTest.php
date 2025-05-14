<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Classic\Behaviors;

use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityStateItem;
use App\Game\Data\EntityTurn;
use App\Game\Data\EntityType;
use App\Game\GameTypes\Classic\Behaviors\FortressCellBehavior;
use Tests\TestCase;

class FortressCellBehaviorTest extends TestCase
{
    use TurnContextBuilderTrait;

    protected FortressCellBehavior $behavior;

    protected function setUp(): void
    {
        parent::setUp();

        $this->behavior = new FortressCellBehavior;
    }

    public function test_on_enter_kills_pirate_when_enemy_already_inside(): void
    {
        /* fortress at (2,2)
         *      enemy pirate (team-2) already there
         *      friendly pirate (team-1) steps in from (2,3) – gets killed
         */
        $turnContext = $this->create2x2TurnContext();
        $fortressPosition = new CellPosition(2, 2);
        $pirateStartPosition = new CellPosition(2, 3);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(2, 2), $this->turnPlayerId);
        $enemyPirate = new Entity(EntityType::Pirate, $fortressPosition, random_int(1000, 2000));

        $turnContext->mergeEntities(collect([$enemyPirate, $pirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($fortressPosition, new Cell(CellType::Fortress, revealed: true));

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $turnContext->getCell($fortressPosition), $fortressPosition);

        $this->assertTrue($turnContext->getTurnEntity()->state->bool(EntityStateItem::IsKilled->value));
    }

    public function test_on_enter_allows_pirate_to_enter_if_fortress_empty(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $fortressPosition = new CellPosition(1, 1);
        $pirateStartPosition = new CellPosition(1, 2);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(1, 1), $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($fortressPosition, new Cell(CellType::Fortress, revealed: true));

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $turnContext->getCell($fortressPosition), $fortressPosition);

        $this->assertFalse($turnContext->getTurnEntity()->state->bool(EntityStateItem::IsKilled->value));
    }

    public function test_on_enter_allows_pirate_to_enter_if_friendly_pirate_inside(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $fortressPosition = new CellPosition(1, 1);
        $pirateStartPosition = new CellPosition(1, 2);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(1, 1), $this->turnPlayerId);
        $friendlyPirate = new Entity(EntityType::Pirate, new CellPosition(2, 2), $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate, $friendlyPirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($fortressPosition, new Cell(CellType::Fortress, revealed: true));

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $turnContext->getCell($fortressPosition), $fortressPosition);

        $this->assertFalse($turnContext->getTurnEntity()->state->bool(EntityStateItem::IsKilled->value));
        $this->assertFalse($turnContext->getEntities()->getEntityByIdOrFail($friendlyPirate->id)->state->bool(EntityStateItem::IsKilled->value));
    }

    public function test_on_enter_allows_pirate_to_enter_if_teammate_pirate_inside(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $fortressPosition = new CellPosition(1, 1);
        $pirateStartPosition = new CellPosition(1, 2);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(1, 1), $this->turnPlayerId);
        $teammatePirate = new Entity(EntityType::Pirate, new CellPosition(2, 2), $this->teammatePlayerId);

        $turnContext->mergeEntities(collect([$pirate, $teammatePirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($fortressPosition, new Cell(CellType::Fortress, revealed: true));

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $turnContext->getCell($fortressPosition), $fortressPosition);

        $this->assertFalse($turnContext->getTurnEntity()->state->bool(EntityStateItem::IsKilled->value));
        $this->assertFalse($turnContext->getEntities()->getEntityByIdOrFail($teammatePirate->id)->state->bool(EntityStateItem::IsKilled->value));
    }

    public function test_allows_turn_returns_false_when_enemy_present(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $fortressPosition = new CellPosition(3, 2);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(3, 1), $this->turnPlayerId);
        $enemyPirate = new Entity(EntityType::Pirate, $fortressPosition, random_int(1000, 2000));

        $turnContext->mergeEntities(collect([$pirate, $enemyPirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($fortressPosition, new Cell(CellType::Fortress, revealed: true));

        $turn = new EntityTurn($pirate->id, $turnContext->getCell($fortressPosition), $fortressPosition);

        $this->assertFalse($this->behavior->allowsTurn($turn, $turnContext));
    }

    public function test_allows_turn_returns_true_when_empty(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $fortressPosition = new CellPosition(1, 3);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(1, 2), $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($fortressPosition, new Cell(CellType::Fortress, revealed: true));

        $turn = new EntityTurn($pirate->id, $turnContext->getCell($fortressPosition), $fortressPosition);

        $this->assertTrue($this->behavior->allowsTurn($turn, $turnContext));
    }

    public function test_allows_turn_returns_true_when_friendly_pirate_inside(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $fortressPosition = new CellPosition(1, 3);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(1, 2), $this->turnPlayerId);
        $teammatePirate = new Entity(EntityType::Pirate, new CellPosition(2, 2), $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate, $teammatePirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($fortressPosition, new Cell(CellType::Fortress, revealed: true));

        $turn = new EntityTurn($pirate->id, $turnContext->getCell($fortressPosition), $fortressPosition);

        $this->assertTrue($this->behavior->allowsTurn($turn, $turnContext));
    }

    public function test_allows_turn_returns_true_when_teammate_pirate_inside(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $fortressPosition = new CellPosition(1, 3);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(1, 2), $this->turnPlayerId);
        $teammatePirate = new Entity(EntityType::Pirate, new CellPosition(2, 2), $this->teammatePlayerId);

        $turnContext->mergeEntities(collect([$pirate, $teammatePirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($fortressPosition, new Cell(CellType::Fortress, revealed: true));

        $turn = new EntityTurn($pirate->id, $turnContext->getCell($fortressPosition), $fortressPosition);

        $this->assertTrue($this->behavior->allowsTurn($turn, $turnContext));
    }

    public function test_allows_entity_to_be_carried_to_returns_false_for_coin(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $fortressPosition = new CellPosition(2, 2);
        $cell = new Cell(CellType::Fortress, revealed: true);

        $carrierPirate = new Entity(EntityType::Pirate, new CellPosition(2, 1), $this->turnPlayerId);
        $coin = new Entity(EntityType::Coin, $carrierPirate->position);

        $turnContext->mergeEntities(collect([$carrierPirate, $coin]));
        $this->assertFalse($this->behavior->allowsEntityToBeCarriedTo($carrierPirate, $coin, $cell, $fortressPosition, $turnContext));
    }

    public function test_allows_entity_to_stay(): void
    {
        $this->assertTrue($this->behavior->allowsEntityToStay());
    }
}
