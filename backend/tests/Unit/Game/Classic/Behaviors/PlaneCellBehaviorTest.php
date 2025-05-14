<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Classic\Behaviors;

use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityTurn;
use App\Game\Data\EntityType;
use App\Game\GameTypes\Classic\Behaviors\PlaneCellBehavior;
use Tests\TestCase;

class PlaneCellBehaviorTest extends TestCase
{
    use TurnContextBuilderTrait;

    private PlaneCellBehavior $behavior;

    protected function setUp(): void
    {
        parent::setUp();

        $this->behavior = new PlaneCellBehavior;
    }

    public function test_on_leave_turns_plane_cell_into_revealed_terrain(): void
    {
        /*  Plane at (2,2) takes off to (2,3)
         *      (2,2)  pirate starts
         *      (2,2)  plane cell — becomes revealed terrain
         *      (2,3)  destination
         */
        $turnContext = $this->create2x2TurnContext();

        $planePosition = new CellPosition(2, 2);
        $destination = new CellPosition(2, 3);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(2, 2), $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);

        $turnContext->setCell($planePosition, new Cell(CellType::Plane, revealed: true));
        $turnContext->setCell($destination, new Cell(CellType::Terrain, revealed: false));

        $this->behavior->onLeave($turnContext, $pirate, $planePosition, $turnContext->getCell($planePosition), $destination);

        $updatedCell = $turnContext->getCell($planePosition);
        $this->assertTrue($updatedCell->type === CellType::Terrain && $updatedCell->revealed);
    }

    public function test_process_possible_turns_returns_only_non_water_cells(): void
    {
        /*  Plane parked at (2,2) should be able to fly to all
         *  interior non-water cells (borders are water).
         */
        $turnContext = $this->create2x2TurnContext();

        $planePosition = new CellPosition(2, 2);
        $pirate = new Entity(EntityType::Pirate, $planePosition, $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($planePosition, new Cell(CellType::Plane, revealed: true));

        $turns = $this->behavior->processPossibleTurns(collect(), $turnContext);

        $this->assertTrue($turns->every(fn (EntityTurn $t) => $t->cell->type !== CellType::Water));
        $this->assertSame(8, $turns->count());
    }

    public function test_allows_entity_to_stay(): void
    {
        $this->assertFalse($this->behavior->allowsEntityToStay());
    }
}
