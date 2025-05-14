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
use App\Game\Data\State;
use App\Game\GameTypes\Classic\Behaviors\BarrelCellBehavior;
use Tests\TestCase;

class BarrelCellBehaviorTest extends TestCase
{
    use TurnContextBuilderTrait;

    private BarrelCellBehavior $behavior;

    protected function setUp(): void
    {
        parent::setUp();
        $this->behavior = new BarrelCellBehavior;
    }

    public function test_on_enter_sets_turns_left_to_one(): void
    {
        /*  Barrel at (2,2):
         *      (2,1)  pirate starts
         *      (2,2)  barrel cell
         */
        $turnContext = $this->create2x2TurnContext();
        $pirateStartPosition = new CellPosition(2, 1);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(2, 2), $this->turnPlayerId);
        $barrelPosition = new CellPosition(2, 2);

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($barrelPosition, new Cell(CellType::Barrel, revealed: true));

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $turnContext->getCell($barrelPosition), $barrelPosition);

        $this->assertSame(1, $turnContext->getTurnEntity()->state->int(EntityStateItem::TurnsOnCellLeft->value));
    }

    public function test_process_possible_turns_blocks_when_turns_left_positive(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $pirate = new Entity(EntityType::Pirate, new CellPosition(2, 1), $this->turnPlayerId, new State([EntityStateItem::TurnsOnCellLeft->value => 1]));

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);

        $result = $this->behavior->processPossibleTurns(collect(), $turnContext);

        $this->assertTrue($result->isEmpty());
    }

    public function test_process_possible_turns_does_not_block_when_turns_left_zero(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $pirate = new Entity(EntityType::Pirate, new CellPosition(2, 1), $this->turnPlayerId, new State([EntityStateItem::TurnsOnCellLeft->value => 0]));

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);

        $possibleTurns = collect([
            new EntityTurn($pirate->id, $turnContext->getCell(new CellPosition(1, 1)), new CellPosition(1, 1)),
            new EntityTurn($pirate->id, $turnContext->getCell(new CellPosition(1, 3)), new CellPosition(1, 3)),
            new EntityTurn($pirate->id, $turnContext->getCell(new CellPosition(2, 1)), new CellPosition(2, 1)),
            new EntityTurn($pirate->id, $turnContext->getCell(new CellPosition(2, 2)), new CellPosition(2, 2)),
            new EntityTurn($pirate->id, $turnContext->getCell(new CellPosition(2, 3)), new CellPosition(2, 3)),
        ]);

        $result = $this->behavior->processPossibleTurns($possibleTurns, $turnContext);

        $this->assertSame($possibleTurns->count(), $result->count());
    }

    public function test_process_possible_turns_does_not_block_when_turns_left_unset(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $pirate = new Entity(EntityType::Pirate, new CellPosition(2, 1), $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);

        $possibleTurns = collect([
            new EntityTurn($pirate->id, $turnContext->getCell(new CellPosition(1, 1)), new CellPosition(1, 1)),
            new EntityTurn($pirate->id, $turnContext->getCell(new CellPosition(1, 3)), new CellPosition(1, 3)),
            new EntityTurn($pirate->id, $turnContext->getCell(new CellPosition(2, 1)), new CellPosition(2, 1)),
            new EntityTurn($pirate->id, $turnContext->getCell(new CellPosition(2, 2)), new CellPosition(2, 2)),
            new EntityTurn($pirate->id, $turnContext->getCell(new CellPosition(2, 3)), new CellPosition(2, 3)),
        ]);

        $result = $this->behavior->processPossibleTurns($possibleTurns, $turnContext);

        $this->assertSame($possibleTurns->count(), $result->count());
    }

    public function test_on_player_turn_over_unsets_flag(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $barrelPosition = new CellPosition(1, 1);

        $pirate = new Entity(EntityType::Pirate, $barrelPosition, $this->turnPlayerId, new State([EntityStateItem::TurnsOnCellLeft->value => 1]));

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($barrelPosition, new Cell(CellType::Barrel, revealed: true));

        $this->behavior->onPlayerTurnOver($turnContext, $pirate, $turnContext->getCell($barrelPosition), $barrelPosition);

        $this->assertSame(0, $turnContext->getTurnEntity()->state->int(EntityStateItem::TurnsOnCellLeft->value));
    }

    public function test_allows_entity_to_stay(): void
    {
        $this->assertTrue($this->behavior->allowsEntityToStay());
    }
}
