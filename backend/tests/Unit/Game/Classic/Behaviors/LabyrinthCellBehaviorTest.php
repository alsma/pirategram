<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Classic\Behaviors;

use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityStateItem;
use App\Game\Data\EntityType;
use App\Game\Data\State;
use App\Game\GameTypes\Classic\Behaviors\LabyrinthCellBehavior;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LabyrinthCellBehaviorTest extends TestCase
{
    use TurnContextBuilderTrait;

    private LabyrinthCellBehavior $behavior;

    protected function setUp(): void
    {
        parent::setUp();

        $this->behavior = new LabyrinthCellBehavior;
    }

    #[DataProvider('labyrinthProvider')]
    public function test_on_enter_sets_initial_turns_left(CellType $cellType, int $expectedTurnsLeft): void
    {
        $turnContext = $this->create2x2TurnContext();
        $labyrinthPosition = new CellPosition(2, 2);
        $pirateStartPosition = new CellPosition(2, 1);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(2, 2), $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($labyrinthPosition, new Cell($cellType, revealed: true));

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $turnContext->getCell($labyrinthPosition), $labyrinthPosition);

        $this->assertSame($expectedTurnsLeft, $turnContext->getTurnEntity()->state->int(EntityStateItem::TurnsOnCellLeft->value));
    }

    public static function labyrinthProvider(): array
    {
        return [
            [CellType::Labyrinth2, 1],
            [CellType::Labyrinth3, 2],
            [CellType::Labyrinth4, 3],
            [CellType::Labyrinth5, 4],
        ];
    }

    public function test_on_enter_decrements_and_unsets_after_zero(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $labyrinthPosition = new CellPosition(1, 1);
        $pirateStartPosition = new CellPosition(1, 0);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(1, 1), $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($labyrinthPosition, new Cell(CellType::Labyrinth2, revealed: true));

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $turnContext->getCell($labyrinthPosition), $labyrinthPosition);

        // simulate second entry (stays on same cell next turn)
        $pirate2 = $turnContext->getTurnEntity();
        $turnContext->setTurnEntity($pirate2);
        $this->behavior->onEnter($turnContext, $pirate2, $labyrinthPosition, $turnContext->getCell($labyrinthPosition), $labyrinthPosition);

        $this->assertSame(0, $turnContext->getTurnEntity()->state->int(EntityStateItem::TurnsOnCellLeft->value));
    }

    public function test_process_possible_turns_restricts_when_turns_left_positive(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $labyrinthPosition = new CellPosition(3, 2);
        $pirate = new Entity(EntityType::Pirate, $labyrinthPosition, $this->turnPlayerId, new State([EntityStateItem::TurnsOnCellLeft->value => 2]));

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($labyrinthPosition, new Cell(CellType::Labyrinth3, revealed: true));

        $turns = $this->behavior->processPossibleTurns(collect(), $turnContext);

        $this->assertSame(1, $turns->count());
        $this->assertTrue($turns->first()->position->is($labyrinthPosition));
    }
}
