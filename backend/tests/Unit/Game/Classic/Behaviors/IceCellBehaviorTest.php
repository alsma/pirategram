<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Classic\Behaviors;

use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityStateItem;
use App\Game\Data\EntityType;
use App\Game\Commands\KillEntityCommand;
use App\Game\Commands\UpdateEntityPositionCommand;
use App\Game\GameTypes\Classic\Behaviors\IceCellBehavior;
use Tests\TestCase;

class IceCellBehaviorTest extends TestCase
{
    use TurnContextBuilderTrait;

    private IceCellBehavior $behavior;

    protected function setUp(): void
    {
        parent::setUp();

        $this->behavior = new IceCellBehavior;
    }

    public function test_on_enter_slides_pirate_one_extra_cell(): void
    {
        /* ice at (2,3)
         *      (2,4) pirate prev
         *      (2,3) pirate enters ice, vector (0,-1)
         *      (2,2) expected final
         */
        $turnContext = $this->create2x2TurnContext();
        $icePosition = new CellPosition(2, 3);
        $pirateStartPosition = new CellPosition(2, 4);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(2, 3), $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($icePosition, new Cell(CellType::Ice, revealed: true));

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $turnContext->getCell($icePosition), $icePosition);

        $this->assertTrue($turnContext->getTurnEntity()->position->is(new CellPosition(2, 2)));

        $command = $turnContext->getAppliedCommands()->last();
        $this->assertInstanceOf(UpdateEntityPositionCommand::class, $command);
        $this->assertTrue($command->newPosition->is(new CellPosition(2, 2)));
    }

    public function test_on_enter_kills_pirate_after_knight_slide_off_board(): void
    {
        /* ice at (3,3)
         *      (1,2) prevPosition
         *      └── vector (+2,+1)  knight “L”
         *      (3,3) icePosition
         *      slide repeats vector → (5,4) outside board → pirate dies
         */
        $turnContext = $this->create2x2TurnContext();
        $icePosition = new CellPosition(3, 3);
        $pirateStartPosition = new CellPosition(1, 2);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(3, 3), $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);
        $turnContext->setCell($icePosition, new Cell(CellType::Ice, revealed: true));

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $turnContext->getCell($icePosition), $icePosition);

        $this->assertTrue($turnContext->getTurnEntity()->state->bool(EntityStateItem::IsKilled->value));

        $command = $turnContext->getAppliedCommands()->last();
        $this->assertInstanceOf(KillEntityCommand::class, $command);
    }

    public function test_carried_coin_allowed_when_current_and_next_cells_revealed(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $icePosition = new CellPosition(2, 2);                        // current cell
        $nextPosition = new CellPosition(2, 3);                        // vector (0,+1)
        $carrier = new Entity(EntityType::Pirate, new CellPosition(2, 1), $this->turnPlayerId);
        $coin = new Entity(EntityType::Coin, $carrier->position);

        $turnContext->mergeEntities(collect([$carrier, $coin]));
        $turnContext->setCell($icePosition, new Cell(CellType::Ice, revealed: true));
        $turnContext->setCell($nextPosition, new Cell(CellType::Terrain, revealed: true));

        $allowed = $this->behavior->allowsEntityToBeCarriedTo($carrier, $coin, $turnContext->getCell($icePosition), $icePosition, $turnContext);

        $this->assertTrue($allowed);
    }

    public function test_carried_coin_blocked_when_current_cell_unrevealed(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $icePosition = new CellPosition(2, 2);
        $nextPosition = new CellPosition(2, 3);
        $carrier = new Entity(EntityType::Pirate, new CellPosition(2, 1), $this->turnPlayerId);
        $coin = new Entity(EntityType::Coin, $carrier->position);

        $turnContext->mergeEntities(collect([$carrier, $coin]));
        $turnContext->setCell($icePosition, new Cell(CellType::Ice, revealed: false));
        $turnContext->setCell($nextPosition, new Cell(CellType::Terrain, revealed: true));

        $this->assertFalse($this->behavior->allowsEntityToBeCarriedTo($carrier, $coin, $turnContext->getCell($icePosition), $icePosition, $turnContext));
    }

    public function test_carried_coin_blocked_when_next_cell_unrevealed(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $icePosition = new CellPosition(2, 2);
        $nextPosition = new CellPosition(2, 3);
        $carrier = new Entity(EntityType::Pirate, new CellPosition(2, 1), $this->turnPlayerId);
        $coin = new Entity(EntityType::Coin, $carrier->position);

        $turnContext->mergeEntities(collect([$carrier, $coin]));
        $turnContext->setCell($icePosition, new Cell(CellType::Ice, revealed: true));
        $turnContext->setCell($nextPosition, new Cell(CellType::Terrain, revealed: false));

        $this->assertFalse($this->behavior->allowsEntityToBeCarriedTo($carrier, $coin, $turnContext->getCell($icePosition), $icePosition, $turnContext));
    }

    public function test_carried_coin_always_allowed_when_next_cell_exists(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $icePosition = new CellPosition(1, 2);
        $nextPosition = new CellPosition(1, 3);
        $carrier = new Entity(EntityType::Pirate, new CellPosition(1, 1), $this->turnPlayerId);
        $coin = new Entity(EntityType::Coin, $carrier->position);

        $turnContext->mergeEntities(collect([$carrier, $coin]));
        $turnContext->setCell($icePosition, new Cell(CellType::Ice, revealed: true));
        $turnContext->setCell($nextPosition, new Cell(CellType::Terrain, revealed: true));

        $this->assertTrue($this->behavior->allowsEntityToBeCarriedTo($carrier, $coin, $turnContext->getCell($icePosition), $icePosition, $turnContext));
    }


    public function test_carried_coin_blocked_when_next_cell_missing(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $icePosition = new CellPosition(4, 4);
        $carrier = new Entity(EntityType::Pirate, new CellPosition(3, 3), $this->turnPlayerId);
        $coin = new Entity(EntityType::Coin, $carrier->position);

        $turnContext->mergeEntities(collect([$carrier, $coin]));
        $turnContext->setCell($icePosition, new Cell(CellType::Ice, revealed: true));

        $this->assertFalse($this->behavior->allowsEntityToBeCarriedTo($carrier, $coin, $turnContext->getCell($icePosition), $icePosition, $turnContext));
    }

    public function test_allows_entity_to_stay(): void
    {
        $this->assertFalse($this->behavior->allowsEntityToStay());
    }
}
