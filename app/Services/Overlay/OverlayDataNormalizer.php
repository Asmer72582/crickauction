<?php

namespace App\Services\Overlay;

/**
 * Converts MatchRoom state into a clean overlay payload.
 * Themes consume only this format — never raw DB schemas.
 */
class OverlayDataNormalizer
{
    public function normalize(array $state, array $meta = []): array
    {
        $battingKey = ($state['battingTeam'] ?? 'A') === 'B' ? 'teamB' : 'teamA';
        $bowlingKey = $battingKey === 'teamA' ? 'teamB' : 'teamA';
        $batting = $state[$battingKey] ?? [];
        $bowling = $state[$bowlingKey] ?? [];
        $totalOvers = (int) ($state['totalOvers'] ?? 20);

        $batsmen = $this->normalizeBatsmen($batting);
        $bowler = $this->normalizeBowler($bowling);
        $lastBall = $this->normalizeLastBall($state);
        $thisOver = array_values($state['thisOver'] ?? []);
        $partnership = $this->partnership($batsmen);
        $fow = $this->fallOfWickets($batting);
        $crr = $this->crr($batting['score'] ?? 0, $batting['overs'] ?? '0.0');
        $target = $state['target'] ?? null;
        $rrr = null;
        $need = null;
        $ballsLeft = null;

        if ($target !== null && ($state['battingTeam'] ?? 'A') === 'B') {
            $need = max(0, (int) $target - (int) ($batting['score'] ?? 0));
            $ballsLeft = $this->ballsRemaining($batting['overs'] ?? '0.0', $totalOvers);
            $rrr = $this->rrr($batting['score'] ?? 0, (int) $target, $batting['overs'] ?? '0.0', $totalOvers);
        }

        return [
            'match' => [
                'id' => $meta['matchId'] ?? null,
                'title' => $state['matchTitle'] ?? 'Live Match',
                'matchNo' => (string) ($state['matchNo'] ?? '1'),
                'status' => match ($state['matchStatus'] ?? null) {
                    'completed' => 'COMPLETED',
                    'innings_break' => 'INNINGS_BREAK',
                    default => 'LIVE',
                },
                'matchStatus' => $state['matchStatus'] ?? 'live',
                'innings' => (int) ($state['innings'] ?? 1),
                'overs' => (string) ($batting['overs'] ?? '0.0'),
                'totalOvers' => $totalOvers,
                'target' => $target,
                'result' => $state['result'] ?? '',
                'visible' => (bool) ($state['visible'] ?? true),
                'powerplay' => (bool) ($state['powerplay'] ?? false),
                'themeId' => $state['themeId'] ?? 'modern',
            ],
            'battingTeam' => [
                'name' => $batting['name'] ?? 'Team A',
                'shortName' => $batting['shortName'] ?? 'TMA',
                'logo' => $batting['logo'] ?? '',
                'primaryColor' => $batting['primaryColor'] ?? '',
                'runs' => (int) ($batting['score'] ?? 0),
                'wickets' => (int) ($batting['wickets'] ?? 0),
                'overs' => (string) ($batting['overs'] ?? '0.0'),
                'crr' => $crr,
                'extras' => $batting['extras'] ?? ['wd' => 0, 'nb' => 0, 'b' => 0, 'lb' => 0],
            ],
            'bowlingTeam' => [
                'name' => $bowling['name'] ?? 'Team B',
                'shortName' => $bowling['shortName'] ?? 'TMB',
                'logo' => $bowling['logo'] ?? '',
                'runs' => (int) ($bowling['score'] ?? 0),
                'wickets' => (int) ($bowling['wickets'] ?? 0),
                'overs' => (string) ($bowling['overs'] ?? '0.0'),
            ],
            'chase' => [
                'target' => $target,
                'need' => $need,
                'ballsLeft' => $ballsLeft,
                'rrr' => $rrr,
            ],
            'batsmen' => $batsmen,
            'bowler' => $bowler,
            'lastBall' => $lastBall,
            'thisOver' => $thisOver,
            'partnership' => $partnership,
            'fallOfWickets' => $fow,
            'branding' => $meta['branding'] ?? [
                'tournamentLogo' => '',
                'sponsorLogo' => '',
                'sponsorText' => '',
                'poweredBy' => '',
            ],
            'version' => $meta['version'] ?? 0,
        ];
    }

    protected function normalizeBatsmen(array $team): array
    {
        $out = [];
        if (! empty($team['batsmen']) && is_array($team['batsmen'])) {
            $strikerId = $team['strikerId'] ?? null;
            $nonStrikerId = $team['nonStrikerId'] ?? null;
            foreach ($team['batsmen'] as $b) {
                if (! empty($b['out'])) {
                    continue;
                }
                if (($b['id'] ?? null) !== $strikerId && ($b['id'] ?? null) !== $nonStrikerId) {
                    continue;
                }
                $runs = (int) ($b['runs'] ?? 0);
                $balls = (int) ($b['balls'] ?? 0);
                $out[] = [
                    'name' => $b['name'] ?? '',
                    'runs' => $runs,
                    'balls' => $balls,
                    'fours' => (int) ($b['fours'] ?? 0),
                    'sixes' => (int) ($b['sixes'] ?? 0),
                    'sr' => $balls > 0 ? round(($runs / $balls) * 100, 1) : 0,
                    'striker' => ($b['id'] ?? null) === $strikerId,
                ];
            }
            // Ensure striker first
            usort($out, fn ($a, $b) => ($b['striker'] <=> $a['striker']));

            return $out;
        }

        // Fallback from derived strings
        foreach (['batsman1', 'batsman2'] as $i => $key) {
            $parsed = $this->parseBatterLine($team[$key] ?? '');
            if ($parsed['name'] === '') {
                continue;
            }
            $out[] = array_merge($parsed, ['striker' => $i === 0]);
        }

        return $out;
    }

    protected function normalizeBowler(array $team): ?array
    {
        if (! empty($team['bowlers']) && ! empty($team['currentBowlerId'])) {
            foreach ($team['bowlers'] as $b) {
                if (($b['id'] ?? null) !== $team['currentBowlerId']) {
                    continue;
                }
                $balls = (int) ($b['balls'] ?? 0);
                $runs = (int) ($b['runs'] ?? 0);
                $overs = intdiv($balls, 6).'.'.($balls % 6);

                return [
                    'name' => $b['name'] ?? '',
                    'overs' => $overs,
                    'maidens' => (int) ($b['maidens'] ?? 0),
                    'runs' => $runs,
                    'wickets' => (int) ($b['wickets'] ?? 0),
                    'economy' => $balls > 0 ? round($runs / ($balls / 6), 2) : 0,
                    'figures' => sprintf('%s - %d - %d - %d', $overs, (int) ($b['maidens'] ?? 0), $runs, (int) ($b['wickets'] ?? 0)),
                ];
            }
        }

        $line = trim((string) ($team['bowler'] ?? ''));
        if ($line === '') {
            return null;
        }

        return [
            'name' => $line,
            'overs' => '0.0',
            'maidens' => 0,
            'runs' => 0,
            'wickets' => 0,
            'economy' => 0,
            'figures' => $line,
        ];
    }

    protected function normalizeLastBall(array $state): ?array
    {
        $over = $state['thisOver'] ?? [];
        if (count($over)) {
            $sym = (string) end($over);

            return $this->symbolToBall($sym);
        }
        $ev = $state['lastEvent'] ?? null;
        if (! is_array($ev)) {
            return null;
        }
        $type = strtoupper((string) ($ev['type'] ?? ''));
        if ($type === 'RUNS' || $type === 'RUN' || $type === 'DOT') {
            $runs = (int) ($ev['runs'] ?? 0);
            if ($runs === 4) {
                return ['type' => 'FOUR', 'runs' => 4, 'label' => 'FOUR'];
            }
            if ($runs === 6) {
                return ['type' => 'SIX', 'runs' => 6, 'label' => 'SIX'];
            }

            return ['type' => (string) $runs, 'runs' => $runs, 'label' => (string) $runs];
        }
        if ($type === 'WICKET') {
            return ['type' => 'WICKET', 'runs' => 0, 'label' => 'WICKET'];
        }
        if ($type === 'WIDE') {
            return ['type' => 'WIDE', 'runs' => (int) ($ev['runs'] ?? 1), 'label' => 'WIDE'];
        }
        if ($type === 'NOBALL') {
            return ['type' => 'NO BALL', 'runs' => (int) ($ev['runs'] ?? 1), 'label' => 'NO BALL'];
        }
        if ($type === 'BYE') {
            return ['type' => 'BYE', 'runs' => (int) ($ev['runs'] ?? 1), 'label' => 'BYE'];
        }
        if ($type === 'LEGBYE') {
            return ['type' => 'LEG BYE', 'runs' => (int) ($ev['runs'] ?? 1), 'label' => 'LEG BYE'];
        }

        return null;
    }

    protected function symbolToBall(string $sym): array
    {
        $s = strtoupper($sym);
        return match ($s) {
            '4' => ['type' => 'FOUR', 'runs' => 4, 'label' => 'FOUR'],
            '6' => ['type' => 'SIX', 'runs' => 6, 'label' => 'SIX'],
            'W' => ['type' => 'WICKET', 'runs' => 0, 'label' => 'WICKET'],
            'WD' => ['type' => 'WIDE', 'runs' => 1, 'label' => 'WIDE'],
            'NB' => ['type' => 'NO BALL', 'runs' => 1, 'label' => 'NO BALL'],
            'B' => ['type' => 'BYE', 'runs' => 1, 'label' => 'BYE'],
            'LB' => ['type' => 'LEG BYE', 'runs' => 1, 'label' => 'LEG BYE'],
            '•', '.', '0' => ['type' => '0', 'runs' => 0, 'label' => '0'],
            default => ['type' => $s, 'runs' => is_numeric($s) ? (int) $s : 0, 'label' => $s],
        };
    }

    protected function partnership(array $batsmen): array
    {
        $runs = 0;
        $balls = 0;
        foreach ($batsmen as $b) {
            $runs += (int) ($b['runs'] ?? 0);
            $balls += (int) ($b['balls'] ?? 0);
        }

        return ['runs' => $runs, 'balls' => $balls];
    }

    protected function fallOfWickets(array $team): array
    {
        $fow = [];
        if (empty($team['batsmen']) || ! is_array($team['batsmen'])) {
            return $fow;
        }
        // Approximate FOW from out batsmen order (score at dismissal not stored — use cumulative out count placeholders)
        $outCount = 0;
        foreach ($team['batsmen'] as $b) {
            if (empty($b['out'])) {
                continue;
            }
            $outCount++;
            // Best-effort: show wicket number; exact score-at-fall needs future tracking
            $fow[] = [
                'wicket' => $outCount,
                'score' => null,
                'player' => $b['name'] ?? '',
                'dismissal' => $b['dismissal'] ?? 'OUT',
                'label' => ($b['name'] ?? 'Batter').' — '.($b['dismissal'] ?? 'OUT'),
            ];
        }

        return $fow;
    }

    protected function parseBatterLine(string $line): array
    {
        $t = trim($line);
        if ($t === '') {
            return ['name' => '', 'runs' => 0, 'balls' => 0, 'fours' => 0, 'sixes' => 0, 'sr' => 0];
        }
        $t = str_replace('*', '', $t);
        if (preg_match('/^(.+?)\s+(\d+)\s*\((\d+)\)$/', $t, $m)) {
            $runs = (int) $m[2];
            $balls = (int) $m[3];

            return [
                'name' => trim($m[1]),
                'runs' => $runs,
                'balls' => $balls,
                'fours' => 0,
                'sixes' => 0,
                'sr' => $balls > 0 ? round(($runs / $balls) * 100, 1) : 0,
            ];
        }

        return ['name' => $t, 'runs' => 0, 'balls' => 0, 'fours' => 0, 'sixes' => 0, 'sr' => 0];
    }

    protected function crr(int $score, string $overs): string
    {
        $o = $this->oversToFloat($overs);
        if ($o <= 0) {
            return '0.00';
        }

        return number_format($score / $o, 2, '.', '');
    }

    protected function rrr(int $score, int $target, string $overs, int $totalOvers): ?string
    {
        $needed = $target - $score;
        $rem = max(0, $totalOvers - $this->oversToFloat($overs));
        if ($needed <= 0 || $rem <= 0) {
            return null;
        }

        return number_format($needed / $rem, 2, '.', '');
    }

    protected function ballsRemaining(string $overs, int $totalOvers): int
    {
        return (int) max(0, ceil(($totalOvers - $this->oversToFloat($overs)) * 6));
    }

    protected function oversToFloat(string $overs): float
    {
        $parts = explode('.', $overs);
        $o = (int) ($parts[0] ?? 0);
        $b = (int) ($parts[1] ?? 0);

        return $o + ($b / 6);
    }
}
