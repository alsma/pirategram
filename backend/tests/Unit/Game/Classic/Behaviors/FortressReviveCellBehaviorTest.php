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
use App\Game\GameTypes\Classic\Behaviors\FortressReviveCellBehavior;
use Tests\TestCase;

class FortressReviveCellBehaviorTest extends FortressCellBehaviorTest
{
    protected function setUp(): void
    {
        TestCase::setUp();

        $this->behavior = new FortressReviveCellBehavior;
    }

    public function test_on_player_turn_over_revives_current_players_pirate_first(): void
    {
        /* fortress-revive at (2,2)
         *  – live pirate A (turn player) stands on fortress and ends turn
         *  – killed pirate B (same player) must be revived to fortress
         *  – killed pirate C (teammate) stays dead
         */
        $turnContext = $this->create2x2TurnContext();
        $fortressPosition = new CellPosition(2, 2);

        $alivePirate1 = new Entity(EntityType::Pirate, $fortressPosition, $this->turnPlayerId);
        $killedPirate2 = new Entity(EntityType::Pirate, new CellPosition(1, 1), $this->turnPlayerId, new State([EntityStateItem::IsKilled->value => true]));
        $killedTeammatePirate = new Entity(EntityType::Pirate, new CellPosition(3, 3), $this->teammatePlayerId, new State([EntityStateItem::IsKilled->value => true]));

        $turnContext->mergeEntities(collect([$alivePirate1, $killedPirate2, $killedTeammatePirate]));
        $turnContext->setTurnEntity($alivePirate1);
        $turnContext->setCell($fortressPosition, new Cell(CellType::ReviveFortress, revealed: true));

        $this->behavior->onPlayerTurnOver($turnContext, $alivePirate1, $turnContext->getCell($fortressPosition), $fortressPosition);

        $updatedPirate2 = $turnContext->getEntities()->getEntityByIdOrFail($killedPirate2->id);
        $updatedTeammatePirate = $turnContext->getEntities()->getEntityByIdOrFail($killedTeammatePirate->id);

        $this->assertFalse($turnContext->getTurnEntity()->state->bool(EntityStateItem::IsKilled->value));
        $this->assertTrue($turnContext->getTurnEntity()->position->is($fortressPosition));
        $this->assertFalse($updatedPirate2->state->bool(EntityStateItem::IsKilled->value));
        $this->assertTrue($updatedPirate2->position->is($fortressPosition));
        $this->assertTrue($updatedTeammatePirate->state->bool(EntityStateItem::IsKilled->value));
    }

    public function test_on_player_turn_over_revives_teammate_when_no_own_pirate(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $fortressPosition = new CellPosition(1, 1);

        $alivePirate1 = new Entity(EntityType::Pirate, $fortressPosition, $this->turnPlayerId);
        $killedTeammatePirate = new Entity(EntityType::Pirate, new CellPosition(2, 2), $this->teammatePlayerId, new State([EntityStateItem::IsKilled->value => true]));

        $turnContext->mergeEntities(collect([$alivePirate1, $killedTeammatePirate]));
        $turnContext->setTurnEntity($alivePirate1);
        $turnContext->setCell($fortressPosition, new Cell(CellType::ReviveFortress, revealed: true));

        $this->behavior->onPlayerTurnOver($turnContext, $alivePirate1, $turnContext->getCell($fortressPosition), $fortressPosition);

        $updatedTeammatePirate = $turnContext->getEntities()->getEntityByIdOrFail($killedTeammatePirate->id);

        $this->assertFalse($turnContext->getTurnEntity()->state->bool(EntityStateItem::IsKilled->value));
        $this->assertTrue($turnContext->getTurnEntity()->position->is($fortressPosition));
        $this->assertFalse($updatedTeammatePirate->state->bool(EntityStateItem::IsKilled->value));
        $this->assertTrue($updatedTeammatePirate->position->is($fortressPosition));
    }

    public function test_on_player_turn_over_does_nothing_if_no_killed_teammates(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $fortressPosition = new CellPosition(3, 1);

        $alivePirate1 = new Entity(EntityType::Pirate, $fortressPosition, $this->turnPlayerId);

        $turnContext->mergeEntities(collect([$alivePirate1]));
        $turnContext->setTurnEntity($alivePirate1);
        $turnContext->setCell($fortressPosition, new Cell(CellType::ReviveFortress, revealed: true));

        $this->behavior->onPlayerTurnOver($turnContext, $alivePirate1, $turnContext->getCell($fortressPosition), $fortressPosition);

        $this->assertTrue($turnContext->getAppliedCommands()->isEmpty());
    }

    public function test_on_player_turn_over_does_not_revive_enemy_pirate(): void
    {
        $turnContext = $this->create2x2TurnContext();
        $fortressPosition = new CellPosition(3, 1);

        $alivePirate = new Entity(EntityType::Pirate, $fortressPosition, $this->turnPlayerId);
        $enemyPirate = new Entity(EntityType::Pirate, new CellPosition(3, 3), random_int(1000, 2000), new State([EntityStateItem::IsKilled->value => true]));

        $turnContext->mergeEntities(collect([$alivePirate, $enemyPirate]));
        $turnContext->setTurnEntity($alivePirate);
        $turnContext->setCell($fortressPosition, new Cell(CellType::ReviveFortress, revealed: true));

        $this->behavior->onPlayerTurnOver($turnContext, $alivePirate, $turnContext->getCell($fortressPosition), $fortressPosition);

        $updatedEnemyPirate = $turnContext->getEntities()->getEntityByIdOrFail($enemyPirate->id);

        $this->assertTrue($updatedEnemyPirate->state->bool(EntityStateItem::IsKilled->value));
        $this->assertTrue($updatedEnemyPirate->position->is(new CellPosition(3, 3)));
        $this->assertTrue($turnContext->getAppliedCommands()->isEmpty());
    }
}
