<?php

declare(strict_types=1);

namespace App\MatchMaking;

class GroupAssembler
{
    public function assemble(string $mode, array $partySnapshots): ?array
    {
        return match ($mode) {
            '1v1' => $this->tryAssemble1v1($partySnapshots),
            '2v2' => $this->tryAssemble2v2($partySnapshots),
            'ffa4' => $this->tryAssembleFfa4($partySnapshots),
            default => null,
        };
    }

    private function tryAssemble1v1(array $partySnapshots): ?array
    {
        // Look for any two parties (sizes summing to 2) with overlapping MMR windows
        // Typically both are size 1; but you could allow size-2 vs two singles if desired for 1v1—here we strictly use singletons
        $singles = array_filter($partySnapshots, fn ($p) => (int) $p['size'] === 1);
        $byMmr = $this->sortByBaseMmr($singles);
        for ($i = 0; $i < count($byMmr) - 1; $i++) {
            $a = $byMmr[$i];
            for ($j = $i + 1; $j < count($byMmr); $j++) {
                $b = $byMmr[$j];
                if ($this->mmrOverlap($a, $b)) {
                    $players = [$a['members'][0]['id'], $b['members'][0]['id']];

                    return [
                        'party_ids' => [(int) $a['party_id'], (int) $b['party_id']],
                        'teams' => [[$players[0]], [$players[1]]],
                        'players' => $players,
                    ];
                }
            }
        }

        return null;
    }

    private function tryAssemble2v2(array $partySnapshots): ?array
    {
        // Find 4 players split into two teams of 2.
        // Allow (2)+(2) or (2)+(1+1) or (1+1)+(1+1)
        $partiesBySize = [
            1 => array_filter($partySnapshots, fn ($p) => (int) $p['size'] === 1),
            2 => array_filter($partySnapshots, fn ($p) => (int) $p['size'] === 2),
        ];

        // (2)+(2)
        foreach ($partiesBySize[2] as $a) {
            foreach ($partiesBySize[2] as $b) {
                if ($a['party_id'] === $b['party_id']) {
                    continue;
                }
                if ($this->mmrOverlap($a, $b)) {
                    $teamA = array_column($a['members'], 'id');
                    $teamB = array_column($b['members'], 'id');

                    return [
                        'party_ids' => [(int) $a['party_id'], (int) $b['party_id']],
                        'teams' => [$teamA, $teamB],
                        'players' => array_merge($teamA, $teamB),
                    ];
                }
            }
        }

        // (2)+(1+1)
        if (!empty($partiesBySize[2]) && count($partiesBySize[1]) >= 2) {
            foreach ($partiesBySize[2] as $duo) {
                $singles = array_values($partiesBySize[1]);
                for ($i = 0; $i < count($singles) - 1; $i++) {
                    for ($j = $i + 1; $j < count($singles); $j++) {
                        $s1 = $singles[$i];
                        $s2 = $singles[$j];
                        if ($this->mmrOverlap3($duo, $s1, $s2)) {
                            $teamA = array_column($duo['members'], 'id');
                            $teamB = [$s1['members'][0]['id'], $s2['members'][0]['id']];

                            return [
                                'party_ids' => [(int) $duo['party_id'], (int) $s1['party_id'], (int) $s2['party_id']],
                                'teams' => [$teamA, $teamB],
                                'players' => array_merge($teamA, $teamB),
                            ];
                        }
                    }
                }
            }
        }

        // (1+1) vs (1+1)
        if (count($partiesBySize[1]) >= 4) {
            $singles = array_values($this->sortByBaseMmr($partiesBySize[1]));
            // naive scan; optimize later
            for ($a = 0; $a < count($singles) - 3; $a++) {
                for ($b = $a + 1; $b < count($singles) - 2; $b++) {
                    for ($c = $b + 1; $c < count($singles) - 1; $c++) {
                        for ($d = $c + 1; $d < count($singles); $d++) {
                            $quad = [$singles[$a], $singles[$b], $singles[$c], $singles[$d]];
                            // Split as (a,b) vs (c,d) if windows overlap roughly
                            if ($this->mmrOverlap($quad[0], $quad[1]) && $this->mmrOverlap($quad[2], $quad[3])) {
                                $teamA = [$quad[0]['members'][0]['id'], $quad[1]['members'][0]['id']];
                                $teamB = [$quad[2]['members'][0]['id'], $quad[3]['members'][0]['id']];

                                return [
                                    'party_ids' => array_map(fn ($p) => (int) $p['party_id'], $quad),
                                    'teams' => [$teamA, $teamB],
                                    'players' => array_merge($teamA, $teamB),
                                ];
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    private function tryAssembleFfa4(array $partySnapshots): ?array
    {
        // Collect parties whose total size = 4 and whose MMR windows overlap pairwise
        $cands = array_values($partySnapshots);
        $n = count($cands);
        for ($a = 0; $a < $n; $a++) {
            for ($b = $a + 1; $b < $n; $b++) {
                for ($c = $b + 1; $c < $n; $c++) {
                    for ($d = $c + 1; $d < $n; $d++) {
                        $set = [$cands[$a], $cands[$b], $cands[$c], $cands[$d]];
                        $size = array_sum(array_map(fn ($p) => (int) $p['size'], $set));
                        if ($size !== 4) {
                            continue;
                        }
                        if ($this->allOverlap($set)) {
                            $players = [];
                            $partyIds = [];
                            foreach ($set as $p) {
                                $partyIds[] = (int) $p['party_id'];
                                foreach ($p['members'] as $m) {
                                    $players[] = $m['id'];
                                }
                            }

                            // ffa: teams => single "team" list (or 4 distinct if needed by game server)
                            return [
                                'party_ids' => $partyIds,
                                'teams' => [$players],
                                'players' => $players,
                            ];
                        }
                    }
                }
            }
        }

        return null;
    }

    private function sortByBaseMmr(array $parties): array
    {
        usort($parties, fn ($a, $b) => $a['base_mmr'] <=> $b['base_mmr']);

        return $parties;
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
        $min = collect($parts)->max('effective_min');
        $max = collect($parts)->min('effective_max');

        return $min <= $max;
    }
}
