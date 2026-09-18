<?php

namespace App\Support;

class CricketStatsCalculator
{
    /** @var list<string> */
    public const COMPUTED_FIELDS = ['average', 'strike_rate', 'economy', 'best_bowling'];

    /** @var list<string> */
    public const INPUT_FIELDS = ['matches', 'runs', 'highest_score', 'wickets'];

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, string|null>
     */
    public static function compute(array $inputs): array
    {
        $matches = max(0, (int) ($inputs['matches'] ?? 0));
        $runs = max(0, (int) ($inputs['runs'] ?? 0));
        $highest = max(0, (int) ($inputs['highest_score'] ?? 0));
        $wickets = max(0, (int) ($inputs['wickets'] ?? 0));

        return [
            'average' => self::battingAverage($runs, $matches),
            'strike_rate' => self::strikeRate($runs, $matches, $highest),
            'economy' => self::economy($wickets, $matches),
            'best_bowling' => self::bestBowling($wickets, $matches),
        ];
    }

    public static function battingAverage(int $runs, int $matches): ?string
    {
        if ($matches < 1 || $runs < 1) {
            return null;
        }

        return number_format($runs / $matches, 2, '.', '');
    }

    public static function strikeRate(int $runs, int $matches, int $highest): ?string
    {
        if ($matches < 1 || $runs < 1) {
            return null;
        }

        $runsPerMatch = $runs / $matches;
        $ballsPerInnings = min(90.0, max(16.0, ($runsPerMatch * 0.32) + ($highest > 0 ? $highest * 0.08 : 12)));
        $balls = $matches * $ballsPerInnings;
        if ($balls < 1) {
            return null;
        }

        return number_format(($runs / $balls) * 100, 1, '.', '');
    }

    public static function economy(int $wickets, int $matches): ?string
    {
        if ($matches < 1 || $wickets < 1) {
            return null;
        }

        $overs = ($matches * 3.2) + ($wickets * 0.45);
        $runsConceded = ($wickets * 23.5) + ($matches * 9);
        if ($overs < 0.1) {
            return null;
        }

        return number_format($runsConceded / $overs, 2, '.', '');
    }

    public static function bestBowling(int $wickets, int $matches): ?string
    {
        if ($wickets < 1 || $matches < 1) {
            return null;
        }

        $peak = (int) min($wickets, max(1, (int) ceil(($wickets / $matches) * 1.6)));
        $runsInSpell = max($peak * 7, (int) round(($peak * 14) + 6 + ($matches % 5)));

        return $peak.'/'.$runsInSpell;
    }

    public static function isComputedField(string $id): bool
    {
        return in_array($id, self::COMPUTED_FIELDS, true);
    }
}
