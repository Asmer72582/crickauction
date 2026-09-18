<?php

namespace App\Services;

/**
 * Overlay graphic packages — two completed themes only.
 *
 * Overlay 01 · Arena Hub  — LOCKED (orange / navy / cyan hub scorebug)
 * Overlay 02 · Neon Prism — cyber lower-third + neon fullscreen stings
 *
 * Package CSS lives in public/overlay-packages/ — never retune a locked package.
 */
class OverlayThemeRegistry
{
    public const OVERLAY_01 = 'arena';
    public const OVERLAY_02 = 'prism';

    public static function all(): array
    {
        return [
            'arena' => self::theme(
                'arena',
                'Overlay 01 · Arena Hub',
                'Completed hub scorebug — navy / orange / cyan (LOCKED)',
                0,
                'hub',
                self::overlay01Colors(),
                [
                    'package' => '01',
                    'status' => 'completed-locked',
                    'css' => '/overlay-packages/overlay-01-arena-hub.css',
                ]
            ),
            'prism' => self::theme(
                'prism',
                'Overlay 02 · Neon Prism',
                'Cyber broadcast — magenta / cyan neon strip + glitch stings',
                0,
                'prism-strip',
                self::overlay02Colors(),
                [
                    'package' => '02',
                    'status' => 'completed-locked',
                    'css' => '/overlay-packages/overlay-02-neon-prism.css',
                ]
            ),
        ];
    }

    /** Locked Overlay 01 palette. */
    public static function overlay01Colors(): array
    {
        return [
            'primary' => '#4964A1',
            'deep' => '#002153',
            'accent' => '#FFA503',
            'glow' => '#00d4ff',
        ];
    }

    /** Locked Overlay 02 palette. */
    public static function overlay02Colors(): array
    {
        return [
            'primary' => '#1a0a2e',
            'deep' => '#050510',
            'accent' => '#ff2d95',
            'glow' => '#00f5ff',
        ];
    }

    private static function theme(
        string $id,
        string $name,
        string $description,
        int $charges,
        string $layout,
        array $colors,
        array $meta = []
    ): array {
        return [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'charges' => $charges,
            'preview' => $id,
            'layout' => $layout,
            'accent' => $colors['accent'],
            'panel' => $colors['primary'],
            'primary' => $colors['primary'],
            'deep' => $colors['deep'],
            'glow' => $colors['glow'],
            'colors' => $colors,
            'package' => $meta['package'] ?? null,
            'status' => $meta['status'] ?? 'layout-flavour',
            'css' => $meta['css'] ?? null,
            'locked' => ($meta['status'] ?? '') === 'completed-locked',
        ];
    }

    public static function get(string $id): array
    {
        $all = self::all();

        return $all[$id] ?? $all[self::OVERLAY_01];
    }

    public static function defaultId(): string
    {
        return self::OVERLAY_01;
    }

    public static function ids(): array
    {
        return array_keys(self::all());
    }

    /** Map legacy flavour IDs to Arena Hub. */
    public static function resolveId(?string $id): string
    {
        $all = self::all();

        return isset($all[$id]) ? $id : self::OVERLAY_01;
    }
}
