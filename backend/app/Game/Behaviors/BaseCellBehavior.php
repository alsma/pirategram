<?php

declare(strict_types=1);

namespace App\Game\Behaviors;

abstract class BaseCellBehavior implements CellBehavior
{
    public function allowsEntityToStay(): bool
    {
        return true;
    }
}
