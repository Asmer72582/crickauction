<?php

namespace App\Services\Overlay;

/**
 * Broadcast theme packages for OBS/vMix overlays.
 * Themes control look only — no scoring logic.
 */
class BroadcastThemeRegistry
{
    public static function all(): array
    {
        return [
            'modern' => [
                'id' => 'modern',
                'name' => 'Modern',
                'description' => 'Professional modern sports broadcast design',
                'scorePosition' => ['x' => 80, 'y' => 820],
                'colors' => [
                    'primary' => '#0ea5e9',
                    'secondary' => '#0f172a',
                    'accent' => '#38bdf8',
                    'text' => '#f8fafc',
                    'danger' => '#ef4444',
                    'panel' => 'rgba(15, 23, 42, 0.92)',
                ],
                'animations' => [
                    'scoreUpdate' => 'slide',
                    'wicket' => 'impact',
                    'four' => 'flash',
                    'six' => 'impact',
                ],
            ],
            'classic' => [
                'id' => 'classic',
                'name' => 'Classic',
                'description' => 'Traditional cricket scoreboard style',
                'scorePosition' => ['x' => 96, 'y' => 860],
                'colors' => [
                    'primary' => '#1e3a5f',
                    'secondary' => '#0a1628',
                    'accent' => '#d4af37',
                    'text' => '#ffffff',
                    'danger' => '#c41e3a',
                    'panel' => 'rgba(8, 18, 38, 0.95)',
                ],
                'animations' => [
                    'scoreUpdate' => 'fade',
                    'wicket' => 'impact',
                    'four' => 'slide',
                    'six' => 'impact',
                ],
            ],
            'minimal' => [
                'id' => 'minimal',
                'name' => 'Minimal',
                'description' => 'Compact overlay — occupies very little screen space',
                'scorePosition' => ['x' => 64, 'y' => 920],
                'colors' => [
                    'primary' => '#64748b',
                    'secondary' => '#111827',
                    'accent' => '#94a3b8',
                    'text' => '#f1f5f9',
                    'danger' => '#f87171',
                    'panel' => 'rgba(17, 24, 39, 0.88)',
                ],
                'animations' => [
                    'scoreUpdate' => 'fade',
                    'wicket' => 'flash',
                    'four' => 'fade',
                    'six' => 'flash',
                ],
            ],
            'premium' => [
                'id' => 'premium',
                'name' => 'Premium',
                'description' => 'Large animated graphics with gradients and transitions',
                'scorePosition' => ['x' => 72, 'y' => 780],
                'colors' => [
                    'primary' => '#a855f7',
                    'secondary' => '#1e1033',
                    'accent' => '#e879f9',
                    'text' => '#fdf4ff',
                    'danger' => '#fb7185',
                    'panel' => 'rgba(30, 16, 51, 0.94)',
                ],
                'animations' => [
                    'scoreUpdate' => 'slide',
                    'wicket' => 'impact',
                    'four' => 'impact',
                    'six' => 'impact',
                ],
            ],
        ];
    }

    public static function get(string $id): array
    {
        $all = self::all();

        return $all[$id] ?? $all['modern'];
    }

    public static function ids(): array
    {
        return array_keys(self::all());
    }

    public static function panels(): array
    {
        return [
            'full' => 'Full Scoreboard',
            'score' => 'Live Score',
            'batsmen' => 'Current Batsmen',
            'bowler' => 'Current Bowler',
            'last_ball' => 'Last Ball',
            'last_over' => 'Last Over',
            'partnership' => 'Partnership',
            'fow' => 'Fall of Wickets',
            'match_info' => 'Match Information',
            'player_card' => 'Player Card',
        ];
    }
}
