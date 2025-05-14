<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Classic\Behaviors;

use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityType;
use App\Game\GameTypes\Classic\Behaviors\CrocodileCellBehavior;
use Tests\TestCase;

class CrocodileCellBehaviorTest extends TestCase
{
    use TurnContextBuilderTrait;

    private CrocodileCellBehavior $behavior;

    protected function setUp(): void
    {
        parent::setUp();

        $this->behavior = new CrocodileCellBehavior;
    }

    public function test_on_enter_pushes_pirate_back_to_previous_position(): void
    {
        /* crocodile at (2,2)
         *      (2,1) pirate starts
         *      (2,2) crocodile cell – pushes back to (2,1)
         */
        $turnContext = $this->create2x2TurnContext();
        $crocodilePosition = new CellPosition(2, 2);
        $pirateStartPosition = new CellPosition(2, 1);

        $pirate = new Entity(EntityType::Pirate, new CellPosition(2, 2), $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($crocodilePosition, new Cell(CellType::Crocodile, revealed: true));

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $turnContext->getCell($crocodilePosition), $crocodilePosition);

        $this->assertTrue($turnContext->getTurnEntity()->position->is($pirateStartPosition));
    }

    public function test_allows_entity_to_stay(): void
    {
        $this->assertFalse($this->behavior->allowsEntityToStay());
    }
}
