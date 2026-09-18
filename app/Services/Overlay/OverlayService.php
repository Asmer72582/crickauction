<?php

namespace App\Services\Overlay;

use App\Models\OverlayConfig;
use App\Models\TournamentMatch;
use App\Services\MatchRoomService;

class OverlayService
{
    public function __construct(
        protected MatchRoomService $rooms,
        protected OverlayDataNormalizer $normalizer
    ) {}

    public function getOrCreateConfig(TournamentMatch $match): OverlayConfig
    {
        $config = OverlayConfig::firstOrCreate(
            ['tournament_match_id' => $match->id],
            [
                'theme_id' => $match->tournament?->theme_id && in_array($match->tournament->theme_id, BroadcastThemeRegistry::ids(), true)
                    ? $match->tournament->theme_id
                    : 'modern',
                'active_panel' => 'full',
                'public_token' => OverlayConfig::makeToken(),
                'branding' => [
                    'tournamentLogo' => '',
                    'sponsorLogo' => '',
                    'sponsorText' => '',
                    'poweredBy' => '',
                ],
                'enabled' => true,
            ]
        );

        // Map old visual theme ids to broadcast themes if needed
        if (! in_array($config->theme_id, BroadcastThemeRegistry::ids(), true)) {
            $config->theme_id = 'modern';
            $config->save();
        }

        return $config;
    }

    public function payload(TournamentMatch $match, ?OverlayConfig $config = null): array
    {
        $config ??= $this->getOrCreateConfig($match);
        $room = $this->rooms->getOrCreate($match->room_id);
        $theme = BroadcastThemeRegistry::get($config->theme_id);

        $data = $this->normalizer->normalize($room->state ?? [], [
            'matchId' => (string) $match->id,
            'version' => $room->version,
            'branding' => $config->branding ?? [],
        ]);

        $data['broadcast'] = [
            'theme' => $theme,
            'panel' => $config->active_panel,
            'enabled' => (bool) $config->enabled,
        ];

        return $data;
    }

    public function verifyToken(TournamentMatch $match, ?string $token): bool
    {
        $config = OverlayConfig::where('tournament_match_id', $match->id)->first();
        if (! $config || ! $config->enabled) {
            return false;
        }
        if (! $token) {
            return false;
        }

        return hash_equals($config->public_token, $token);
    }
}
