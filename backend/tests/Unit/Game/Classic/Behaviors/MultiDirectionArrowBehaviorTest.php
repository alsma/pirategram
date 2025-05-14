<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Classic\Behaviors;

use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\ContextData;
use App\Game\Data\Entity;
use App\Game\Data\EntityTurn;
use App\Game\Data\EntityType;
use App\Game\Data\Vector;
use App\Game\GameTypes\Classic\Behaviors\MultiDirectionArrowBehavior;
use Illuminate\Support\Collection;
use Tests\TestCase;

class MultiDirectionArrowBehaviorTest extends TestCase
{
    use TurnContextBuilderTrait;

    private MultiDirectionArrowBehavior $behavior;

    protected function setUp(): void
    {
        parent::setUp();

        $this->behavior = new MultiDirectionArrowBehavior;
    }

    private function buildTurnsAround(Collection $vectors, Entity $pirate, $turnContext): Collection
    {
        return $vectors->map(function (Vector $v) use ($pirate, $turnContext) {
            $p = $pirate->position->add($v);
            $c = $turnContext->getCell($p);

            return $c ? new EntityTurn($pirate->id, $c, $p) : null;
        })->filter();
    }

    public function test_arrow2_horizontal_filters_turns(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $arrowPosition = new CellPosition(2, 2);
        $pirate = new Entity(EntityType::Pirate, $arrowPosition, $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);

        $arrowCell = new Cell(CellType::Arrow2, revealed: true, direction: 0);
        $turnContext->setCell($arrowPosition, $arrowCell);
        $turnContext->mergeData(new ContextData(['currentCell' => $arrowCell]));

        $possible = $this->buildTurnsAround(Vector::createAroundVectors(), $pirate, $turnContext);

        $turns = $this->behavior->processPossibleTurns($possible, $turnContext);
        $destinations = $turns->map->position;

        $expectedDestinations = collect([
            new CellPosition(1, 2),
            new CellPosition(3, 2),
        ]);

        $this->assertSame(2, $turns->count());
        $this->assertEqualsCanonicalizing($expectedDestinations, $destinations);
    }

    public function test_arrow3_rotated_filters_turns(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $arrowPosition = new CellPosition(2, 2);
        $pirate = new Entity(EntityType::Pirate, $arrowPosition, $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);

        $arrowCell = new Cell(CellType::Arrow3, revealed: true, direction: 1); // 90° rotation
        $turnContext->setCell($arrowPosition, $arrowCell);
        $turnContext->mergeData(new ContextData(['currentCell' => $arrowCell]));

        $possible = $this->buildTurnsAround(Vector::createAroundVectors(), $pirate, $turnContext);

        $turns = $this->behavior->processPossibleTurns($possible, $turnContext);
        $destinations = $turns->map->position;

        $expectedDestinations = collect([
            new CellPosition(1, 2),
            new CellPosition(2, 3),
            new CellPosition(3, 1),
        ]);

        $this->assertSame(3, $turns->count());
        $this->assertEqualsCanonicalizing($expectedDestinations, $destinations);
    }

    public function test_allows_entity_to_stay(): void
    {
        $this->assertFalse($this->behavior->allowsEntityToStay());
    }
}
