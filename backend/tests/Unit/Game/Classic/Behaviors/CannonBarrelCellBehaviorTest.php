<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Classic\Behaviors;

use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityType;
use App\Game\GameTypes\Classic\Behaviors\CannonBarrelCellBehavior;
use Tests\TestCase;

class CannonBarrelCellBehaviorTest extends TestCase
{
    use TurnContextBuilderTrait;

    private CannonBarrelCellBehavior $behavior;

    protected function setUp(): void
    {
        parent::setUp();
        $this->behavior = new CannonBarrelCellBehavior;
    }

    public function test_on_enter_direction_0_shoots_pirate_north_until_water(): void
    {
        /* cannon at (2,2) fires north (direction 0)
         *      (2,3) pirate starts (terrain)
         *      (2,2) cannon cell
         *      (2,0) expected landing (first water row)
         */
        $turnContext = $this->create2x2TurnContext();

        $cannonPosition = new CellPosition(2, 2);
        $pirateStartPosition = new CellPosition(2, 3);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(2, 2), $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($cannonPosition, new Cell(CellType::CannonBarrel, revealed: true, direction: 0));

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $turnContext->getCell($cannonPosition), $cannonPosition);

        $this->assertTrue($turnContext->getTurnEntity()->position->is(new CellPosition(2, 0)));
    }

    public function test_on_enter_direction_1_shoots_pirate_east_until_water(): void
    {
        /* cannon at (2,2) fires east (direction 1)
         *      (1,2) pirate starts (terrain)
         *      (2,2) cannon cell
         *      (4,2) expected landing (first water col)
         */
        $turnContext = $this->create2x2TurnContext();

        $cannonPosition = new CellPosition(2, 2);
        $pirateStartPosition = new CellPosition(1, 2);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(2, 2), $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($cannonPosition, new Cell(CellType::CannonBarrel, revealed: true, direction: 1));

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $turnContext->getCell($cannonPosition), $cannonPosition);

        $this->assertTrue($turnContext->getTurnEntity()->position->is(new CellPosition(4, 2)));
    }

    public function test_allows_entity_to_stay(): void
    {
        $this->assertFalse($this->behavior->allowsEntityToStay());
    }
}
