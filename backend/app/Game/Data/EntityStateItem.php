<?php

declare(strict_types=1);

namespace App\Game\Data;

enum EntityStateItem: string
{
    case IsKilled = 'isKilled';
    case TurnsOnCell = 'turnsOnCell';
    case TurnsOnCellLeft = 'turnsOnCellLeft';
    case StuckInTrap = 'stuckInTrap';
}
