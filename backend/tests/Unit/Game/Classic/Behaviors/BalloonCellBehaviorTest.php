<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Classic\Behaviors;

use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityType;
use App\Game\GameTypes\Classic\Behaviors\BalloonCellBehavior;
use Tests\TestCase;

class BalloonCellBehaviorTest extends TestCase
{
    use TurnContextBuilderTrait;

    private BalloonCellBehavior $behavior;

    protected function setUp(): void
    {
        parent::setUp();
        $this->behavior = new BalloonCellBehavior;
    }

    public function test_on_enter_moves_pirate_to_own_ship(): void
    {
        /*  Balloon teleport
         *      (0,2)  ← friendly ship (target position)
         *      (2,2)  ← balloon cell
         */
        $turnContext = $this->create2x2TurnContext();

        $balloonPosition = new CellPosition(2, 2);
        $pirateStart = new CellPosition(2, 2);

        $pirate = new Entity(EntityType::Pirate, $pirateStart, $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($balloonPosition, new Cell(CellType::Balloon, revealed: true));

        $this->behavior->onEnter($turnContext, $pirate, $pirateStart, $turnContext->getCell($balloonPosition), $balloonPosition);

        $this->assertTrue($turnContext->getTurnEntity()->position->is(new CellPosition(2, 0)));
    }

    public function test_allows_entity_to_stay(): void
    {
        $this->assertFalse($this->behavior->allowsEntityToStay());
    }
}
