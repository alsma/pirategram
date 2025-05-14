<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Classic\Behaviors;

use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityType;
use App\Game\GameTypes\Classic\Behaviors\KnightCellBehavior;
use Tests\TestCase;

class KnightCellBehaviorTest extends TestCase
{
    use TurnContextBuilderTrait;

    private KnightCellBehavior $behavior;

    protected function setUp(): void
    {
        parent::setUp();

        $this->behavior = new KnightCellBehavior;
    }

    public function test_process_possible_turns_on_land(): void
    {
        /* knight at (1,1) – eight L-vectors:
         *   terrain landings expected: (3,2) and (2,3)
         *   water or off-board squares discarded
         */
        $turnContext = $this->create2x2TurnContext();
        $knightPosition = new CellPosition(1, 1);
        $knight = new Entity(EntityType::Pirate, $knightPosition, $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$knight]));
        $turnContext->setTurnEntity($knight);

        $turns = $this->behavior->processPossibleTurns(collect(), $turnContext);
        $destinations = $turns->map->position;

        $expectedDestinations = collect([
            new CellPosition(3, 2),
            new CellPosition(2, 3),
        ]);

        $this->assertEqualsCanonicalizing($expectedDestinations, $destinations);
    }

    public function test_process_possible_turns_returns_only_water_with_friendly_ship(): void
    {
        /* knight at (2,2) – all 8 L-moves land on border water.
         * Place friendly ship at (4,3). Expect only (4,3) in result.
         */
        $turnContext = $this->create2x2TurnContext();
        $knightPosition = new CellPosition(2, 2);
        $shipPosition = new CellPosition(4, 3);

        $knight = new Entity(EntityType::Pirate, $knightPosition, $this->turnPlayerId);
        $ship = new Entity(EntityType::Ship, $shipPosition, $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$knight, $ship]));
        $turnContext->setTurnEntity($knight);
        $turnContext->setCell($shipPosition, new Cell(CellType::Water, revealed: true));

        $turns = $this->behavior->processPossibleTurns(collect(), $turnContext);

        $this->assertSame(1, $turns->count());
        $this->assertTrue($turns->first()->position->is($shipPosition));
    }

    public function test_process_possible_turns_empty_when_no_friendly_ships_on_water(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $knightPosition = new CellPosition(2, 2);
        $knight = new Entity(EntityType::Pirate, $knightPosition, $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$knight]));
        $turnContext->setTurnEntity($knight);

        $turns = $this->behavior->processPossibleTurns(collect(), $turnContext);

        $this->assertTrue($turns->isEmpty());
    }

    public function test_allows_entity_to_stay(): void
    {
        $this->assertFalse($this->behavior->allowsEntityToStay());
    }
}
