<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Classic\Behaviors;

use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityType;
use App\Game\GameTypes\Classic\Behaviors\SingleArrowCellBehavior;
use Tests\TestCase;

class SingleArrowCellBehaviorTest extends TestCase
{
    use TurnContextBuilderTrait;

    private SingleArrowCellBehavior $behavior;

    protected function setUp(): void
    {
        parent::setUp();

        $this->behavior = new SingleArrowCellBehavior;
    }

    public function test_on_enter_arrow1_moves_pirate_north(): void
    {
        /*  Arrow1 (↑)
         *      (2,0)  expected position
         *      (2,1)  arrow cell
         *      (2,2)  pirate starts
         */
        $turnContext = $this->create2x2TurnContext();

        $arrowPosition = new CellPosition(2, 1);
        $pirateStartPosition = new CellPosition(2, 2);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(2, 1), $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($arrowPosition, new Cell(CellType::Arrow1, revealed: true, direction: 0));

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $turnContext->getCell($arrowPosition), $arrowPosition);

        $this->assertTrue($turnContext->getTurnEntity()->position->is(new CellPosition(2, 0)));
    }

    public function test_on_enter_arrow1_rotated_moves_pirate_south(): void
    {
        /*  Arrow1 rotated 180° (direction=2)
         *      (2,0)  pirate starts
         *      (2,1)  arrow cell
         *      (2,2)  expected position
         */
        $turnContext = $this->create2x2TurnContext();

        $arrowPosition = new CellPosition(2, 1);
        $pirateStartPosition = new CellPosition(2, 2);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(2, 0), $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($arrowPosition, new Cell(CellType::Arrow1, revealed: true, direction: 2));

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $turnContext->getCell($arrowPosition), $arrowPosition);

        $this->assertTrue($turnContext->getTurnEntity()->position->is(new CellPosition(2, 2)));
    }

    public function test_on_enter_arrow1_diagonal_moves_pirate_north_east(): void
    {
        /*  Arrow1Diagonal (↗)
         *      (2,0)  expected position
         *      (1,1)  arrow cell
         *      (0,2)  pirate starts
         */
        $turnContext = $this->create2x2TurnContext();

        $arrowPosition = new CellPosition(1, 1);
        $pirateStartPosition = new CellPosition(0, 2);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(1, 1), $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($arrowPosition, new Cell(CellType::Arrow1Diagonal, revealed: true, direction: 0));

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $turnContext->getCell($arrowPosition), $arrowPosition);

        $this->assertTrue($turnContext->getTurnEntity()->position->is(new CellPosition(2, 0)));
    }

    public function test_allows_entity_to_stay(): void
    {
        $this->assertFalse($this->behavior->allowsEntityToStay());
    }
}
