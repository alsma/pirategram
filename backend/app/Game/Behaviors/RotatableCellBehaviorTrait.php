<?php

declare(strict_types=1);

namespace App\Game\Behaviors;

use App\Game\Data\Vector;
use Illuminate\Support\Collection;

trait RotatableCellBehaviorTrait
{
    private function rotateVector(int $direction, Vector $vector): Vector
    {
        // Convert direction to radians: 0 -> 0, 1 -> π/2, 2 -> π, 3 -> 3π/2
        $angle = $direction * M_PI / 2;

        // Apply 2D rotation matrix
        $col = (int) round($vector->col * cos($angle) - $vector->row * sin($angle));
        $row = (int) round($vector->col * sin($angle) + $vector->row * cos($angle));

        return new Vector($col, $row);
    }

    private function rotateVectors(int $direction, Collection $vectors): Collection
    {
        return $vectors->map(fn (Vector $v) => $this->rotateVector($direction, $v));
    }
}
