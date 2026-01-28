<?php

declare(strict_types=1);

namespace App\MatchMaking;

use App\MatchMaking\ValueObjects\GameMode;

class GroupAssembler
{
    /**
     * @param  array<string,array>  $groupSnapshots  keyed by group_key ('u:42' or 'p:10')
     *                                               Each snapshot must have:
     *                                               - group_key: string
     *                                               - size: int
     *                                               - base_mmr: int
     *                                               - effective_min: int
     *                                               - effective_max: int
     *                                               - members: array<array{user_id:int, mmr:int}>
     *                                               - enqueued_at: int (timestamp)
     */
    public function assemble(GameMode $mode, array $groupSnapshots): ?array
    {
        return match ($mode) {
            GameMode::OneOnOne => $this->tryAssemble1v1($groupSnapshots),
            GameMode::TwoVsTwo => $this->tryAssemble2v2($groupSnapshots),
            GameMode::FreeForAll4 => $this->tryAssembleFfa4($groupSnapshots),
        };
    }

    private function tryAssemble1v1(array $groups): ?array
    {
        // strictly singles
        $singles = array_filter($groups, fn ($g) => (int) $g['size'] === 1);

        $byMmr = $this->sortByBaseMmr($singles);
        $n = count($byMmr);

        $bestMatch = null;
        $bestScore = PHP_INT_MIN;

        for ($i = 0; $i < $n - 1; $i++) {
            $a = $byMmr[$i];
            for ($j = $i + 1; $j < $n; $j++) {
                $b = $byMmr[$j];
                if ($a['group_key'] === $b['group_key']) {
                    continue;
                }
                if ($this->mmrOverlap($a, $b)) {
                    $score = $this->scoreMatch([$a, $b]);
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $pA = $a['members'][0]['user_id'];
                        $pB = $b['members'][0]['user_id'];

                        $bestMatch = [
                            'group_ids' => [$a['group_key'], $b['group_key']],
                            'teams' => [[$pA], [$pB]],
                            'players' => [$pA, $pB],
                        ];
                    }
                }
            }
        }

        return $bestMatch;
    }

    private function tryAssemble2v2(array $groups): ?array
    {
        // 4 players split 2v2; allow (2+2), (2 + 1 + 1), or (1+1) vs (1+1)
        $bySize = [
            1 => array_filter($groups, fn ($g) => (int) $g['size'] === 1),
            2 => array_filter($groups, fn ($g) => (int) $g['size'] === 2),
        ];

        $bestMatch = null;
        $bestScore = PHP_INT_MIN;

        // (2) + (2)
        foreach ($bySize[2] as $a) {
            foreach ($bySize[2] as $b) {
                if ($a['group_key'] === $b['group_key']) {
                    continue;
                }
                if ($this->mmrOverlap($a, $b)) {
                    $score = $this->scoreMatch([$a, $b]);
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $teamA = array_column($a['members'], 'user_id');
                        $teamB = array_column($b['members'], 'user_id');

                        $bestMatch = [
                            'group_ids' => [$a['group_key'], $b['group_key']],
                            'teams' => [$teamA, $teamB],
                            'players' => array_merge($teamA, $teamB),
                        ];
                    }
                }
            }
        }

        // (2) + (1 + 1)
        if (!empty($bySize[2]) && count($bySize[1]) >= 2) {
            foreach ($bySize[2] as $duo) {
                $singles = array_values($bySize[1]);
                for ($i = 0; $i < count($singles) - 1; $i++) {
                    for ($j = $i + 1; $j < count($singles); $j++) {
                        $s1 = $singles[$i];
                        $s2 = $singles[$j];
                        if ($duo['group_key'] === $s1['group_key'] || $duo['group_key'] === $s2['group_key'] || $s1['group_key'] === $s2['group_key']) {
                            continue;
                        }
                        if ($this->mmrOverlap3($duo, $s1, $s2)) {
                            $score = $this->scoreMatch([$duo, $s1, $s2]);
                            if ($score > $bestScore) {
                                $bestScore = $score;
                                $teamA = array_column($duo['members'], 'user_id');
                                $teamB = [$s1['members'][0]['user_id'], $s2['members'][0]['user_id']];

                                $bestMatch = [
                                    'group_ids' => [$duo['group_key'], $s1['group_key'], $s2['group_key']],
                                    'teams' => [$teamA, $teamB],
                                    'players' => array_merge($teamA, $teamB),
                                ];
                            }
                        }
                    }
                }
            }
        }

        // (1+1) vs (1+1)
        if (count($bySize[1]) >= 4) {
            $singles = array_values($this->sortByBaseMmr($bySize[1]));
            $n = count($singles);
            for ($a = 0; $a < $n - 3; $a++) {
                for ($b = $a + 1; $b < $n - 2; $b++) {
                    for ($c = $b + 1; $c < $n - 1; $c++) {
                        for ($d = $c + 1; $d < $n; $d++) {
                            $g = [$singles[$a], $singles[$b], $singles[$c], $singles[$d]];
                            // Simple pairing (a,b) vs (c,d) with overlap check
                            if ($this->mmrOverlap($g[0], $g[1]) && $this->mmrOverlap($g[2], $g[3])) {
                                $score = $this->scoreMatch($g);
                                if ($score > $bestScore) {
                                    $bestScore = $score;
                                    $teamA = [$g[0]['members'][0]['user_id'], $g[1]['members'][0]['user_id']];
                                    $teamB = [$g[2]['members'][0]['user_id'], $g[3]['members'][0]['user_id']];

                                    $bestMatch = [
                                        'group_ids' => array_map(fn ($x) => $x['group_key'], $g),
                                        'teams' => [$teamA, $teamB],
                                        'players' => array_merge($teamA, $teamB),
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }

        return $bestMatch;
    }

    private function tryAssembleFfa4(array $groups): ?array
    {
        // Any combination summing to size=4 with overlapping windows
        $cands = array_values($groups);
        $n = count($cands);

        $bestMatch = null;
        $bestScore = PHP_INT_MIN;

        for ($a = 0; $a < $n; $a++) {
            for ($b = $a + 1; $b < $n; $b++) {
                for ($c = $b + 1; $c < $n; $c++) {
                    for ($d = $c + 1; $d < $n; $d++) {
                        $set = [$cands[$a], $cands[$b], $cands[$c], $cands[$d]];
                        $size = array_sum(array_map(fn ($g) => (int) $g['size'], $set));
                        if ($size !== 4) {
                            continue;
                        }
                        if ($this->allOverlap($set)) {
                            $score = $this->scoreMatch($set);
                            if ($score > $bestScore) {
                                $bestScore = $score;
                                $players = [];
                                $groupIds = [];
                                foreach ($set as $g) {
                                    $groupIds[] = $g['group_key'];
                                    foreach ($g['members'] as $m) {
                                        $players[] = $m['user_id'];
                                    }
                                }

                                // ffa: either single team of 4 or 4 distinct slots as your game server expects
                                $bestMatch = [
                                    'group_ids' => $groupIds,
                                    'teams' => [$players],
                                    'players' => $players,
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $bestMatch;
    }

    /**
     * Score a potential match. Higher is better.
     *
     * Factors:
     * - MMR closeness (lower spread = higher score)
     * - Wait time balance (longer waiters should be prioritized)
     *
     * @param  array  $groups  Array of group snapshots in the match
     */
    private function scoreMatch(array $groups): float
    {
        $mmrValues = [];
        $waitTimes = [];
        $now = now()->timestamp;

        foreach ($groups as $g) {
            $mmrValues[] = (int) $g['base_mmr'];
            $enqueued = (int) ($g['enqueued_at'] ?? $now);
            $waitTimes[] = max(0, $now - $enqueued);
        }

        // MMR spread penalty: penalize large differences
        $mmrSpread = max($mmrValues) - min($mmrValues);
        $mmrScore = -$mmrSpread; // negative because lower spread is better

        // Wait time bonus: reward matches that include long-waiting players
        $avgWaitTime = array_sum($waitTimes) / count($waitTimes);
        $waitScore = $avgWaitTime * 0.5; // 0.5 points per second of average wait

        // Combined score
        return $mmrScore + $waitScore;
    }

    /** @param array<int,array> $groups */
    private function sortByBaseMmr(array $groups): array
    {
        usort($groups, fn ($a, $b) => $a['base_mmr'] <=> $b['base_mmr']);

        return $groups;
    }

    private function mmrOverlap(array $a, array $b): bool
    {
        return !($a['effective_max'] < $b['effective_min'] || $b['effective_max'] < $a['effective_min']);
    }

    private function mmrOverlap3(array $a, array $b, array $c): bool
    {
        return $this->mmrOverlap($a, $b) && $this->mmrOverlap($a, $c) && $this->mmrOverlap($b, $c);
    }

    private function allOverlap(array $parts): bool
    {
        // pairwise overlap via interval intersection
        $min = collect($parts)->max('effective_min');
        $max = collect($parts)->min('effective_max');

        return $min <= $max;
    }
}
