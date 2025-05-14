<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Classic\Behaviors;

use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityStateItem;
use App\Game\Data\EntityType;
use App\Game\GameTypes\Classic\Behaviors\OgreCellBehavior;
use Tests\TestCase;

class OgreCellBehaviorTest extends TestCase
{
    use TurnContextBuilderTrait;

    private OgreCellBehavior $behavior;

    protected function setUp(): void
    {
        parent::setUp();

        $this->behavior = new OgreCellBehavior;
    }

    public function test_on_enter_kills_pirate(): void
    {
        /*  Ogre cell
         *      (1,0)
         *      (1,1)  ← ogre cell (kills)
         *  P→  (1,2)  ← pirate starts one cell before ogre
         */
        $turnContext = $this->create2x2TurnContext();

        $pirateStartPosition = new CellPosition(1, 2);
        $pirate = new Entity(EntityType::Pirate, new CellPosition(1, 1), $this->turnPlayerId);
        $turnContext->mergeEntities(collect([$pirate]));
        $turnContext->setTurnEntity($pirate);

        $cellPosition = new CellPosition(1, 1);
        $cell = new Cell(CellType::Ogre, revealed: true);
        $turnContext->setCell($cellPosition, $cell);

        $this->behavior->onEnter($turnContext, $pirate, $pirateStartPosition, $cell, $cellPosition);

        $this->assertTrue($turnContext->getTurnEntity()->state->bool(EntityStateItem::IsKilled->value));
    }
}
