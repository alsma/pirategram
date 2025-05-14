<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Classic\Behaviors;

use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityType;
use App\Game\GameTypes\Classic\Behaviors\GoldCellBehavior;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoldCellBehaviorTest extends TestCase
{
    use TurnContextBuilderTrait;

    private GoldCellBehavior $behavior;

    protected function setUp(): void
    {
        parent::setUp();

        $this->behavior = new GoldCellBehavior;
    }

    #[DataProvider('goldCellsProvider')]
    public function test_on_enter_spawns_expected_number_of_coins(CellType $cellType, int $expectedCoins): void
    {
        /* gold cell at (2,2)
         *      (2,1) pirate starts
         *      (2,2) unrevealed gold cell (type varies)
         */
        $turnContext = $this->create2x2TurnContext();
        $goldPosition = new CellPosition(2, 2);
        $pirateStartPosition = new CellPosition(2, 1);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(2, 2), $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($goldPosition, new Cell($cellType, revealed: false));

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $turnContext->getCell($goldPosition), $goldPosition);

        $coins = $turnContext->getEntities()->filter(fn (Entity $e) => $e->type === EntityType::Coin && $e->position->is($goldPosition));

        $this->assertSame($expectedCoins, $coins->count());
    }

    public static function goldCellsProvider(): array
    {
        return [
            [CellType::Gold1, 1],
            [CellType::Gold2, 2],
            [CellType::Gold3, 3],
            [CellType::Gold4, 4],
            [CellType::Gold5, 5],
        ];
    }

    public function test_on_enter_does_not_spawn_if_cell_already_revealed(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $goldPosition = new CellPosition(1, 1);
        $pirateStartPosition = new CellPosition(1, 2);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(1, 1), $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($goldPosition, new Cell(CellType::Gold3, revealed: true));

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $turnContext->getCell($goldPosition), $goldPosition);

        $coins = $turnContext->getEntities()->filter(fn (Entity $e) => $e->type === EntityType::Coin && $e->position->is($goldPosition));

        $this->assertTrue($coins->isEmpty());
    }
}
