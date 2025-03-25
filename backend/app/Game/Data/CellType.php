<?php

declare(strict_types=1);

namespace App\Game\Data;

enum CellType: string
{
    case Water = 'water';
    case Terrain = 'terrain';
    case Arrow1 = 'arrow1';
    case Arrow1Diagonal = 'arrow1diagonal';
    case Arrow2 = 'arrow2';
    case Arrow2Diagonal = 'arrow2diagonal';
    case Arrow3 = 'arrow3';
    case Arrow4 = 'arrow4';
    case Arrow4Diagonal = 'arrow4diagonal';
    case Knight = 'knight';
    case Labyrinth2 = 'labyrinth2';
    case Labyrinth3 = 'labyrinth3';
    case Labyrinth4 = 'labyrinth4';
    case Labyrinth5 = 'labyrinth5';
    case Ice = 'ice';
    case Trap = 'trap';
    case Ogre = 'ogre';
    case Fortress = 'fortress';
    case ReviveFortress = 'reviveFortress';
    case Gold1 = 'gold1';
    case Gold2 = 'gold2';
    case Gold3 = 'gold3';
    case Gold4 = 'gold4';
    case Gold5 = 'gold5';
    case Plane = 'plane';
    case Balloon = 'balloon';
    case Barrel = 'barrel';
    case CannonBarrel = 'cannonBarrel';
    case Crocodile = 'crocodile';
}
