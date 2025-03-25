<?php

declare(strict_types=1);

namespace App\User;

class UsernameGenerator
{
    private const array ADJECTIVES = [
        'Brave', 'Clever', 'Fierce', 'Swift', 'Mighty',
        'Silent', 'Bold', 'Lucky', 'Sly', 'Witty',
        'Fearless', 'Heroic', 'Daring', 'Noble', 'Quick',
        'Crafty', 'Stealthy', 'Dashing', 'Legendary', 'Vigilant',
        'Energetic', 'Mysterious', 'Epic', 'Dynamic', 'Gallant',
        'Shadowy', 'Valiant', 'Adventurous', 'Astute', 'Resolute',
        'Charismatic', 'Stalwart', 'Cunning', 'Ruthless', 'Intrepid',
        'Shrewd', 'Unyielding', 'Ambitious', 'Determined', 'Majestic',
    ];

    private const array NOUNS = [
        'Pirate', 'Explorer', 'Hunter', 'Raider',
        'Sailor', 'Adventurer', 'Captain', 'Scout',
        'Pathfinder', 'Navigator', 'Seafarer', 'Ranger',
        'Warrior', 'Buccaneer', 'Outlaw', 'Corsair',
        'TreasureSeeker', 'Cartographer', 'Tracker', 'Wayfarer',
        'Vanguard', 'Pioneer', 'Mariner', 'Swashbuckler', 'Mercenary',
        'Challenger', 'Duelist', 'Pathfinder', 'Gunman', 'Marksman',
        'Skirmisher', 'Wanderer', 'Rover', 'Tactician', 'Dreamer',
        'Striker', 'Sentinel', 'Guardian', 'Voyager', 'Plunderer',
    ];

    private const array FORMAT = [
        '$rand$num',
        '$adj$num',
        '$noun$num',
        '$adj$num$noun',
        '$adj$noun$num',
    ];

    private const int MAX_TRIES = 10;

    public function generateUsername(): string
    {
        $tries = self::MAX_TRIES;

        do {
            $num = random_int(1, 10_000);
            $rand = $this->generateRandomText();
            $noun = array_random(self::NOUNS);
            $adj = array_random(self::ADJECTIVES);

            $format = array_random(self::FORMAT);

            $username = str_replace(
                ['$num', '$rand', '$adj', '$noun'],
                [$num, $rand, $adj, $noun],
                $format
            );

            $isUsernameBad = false;
            $tries--;
        } while ($isUsernameBad && $tries);

        return $username;
    }

    private function generateRandomText(): string
    {
        $length = random_int(7, 10);
        $vowels = 'aeiouy';
        $consonants = 'bcdfghjklmnpqrstvwxyz';
        $text = '';

        for ($i = 0; $i < $length; $i++) {
            $text .= $i % 2 === 0
                ? $vowels[random_int(0, strlen($vowels) - 1)]
                : $consonants[random_int(0, strlen($consonants) - 1)];
        }

        return ucfirst($text);
    }
}
