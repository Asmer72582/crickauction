<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TournamentMatch;
use App\Services\MatchRoomService;
use App\Services\Overlay\BroadcastThemeRegistry;
use App\Services\Overlay\OverlayService;
use Illuminate\Http\Request;

/**
 * Public overlay endpoints — no auth, token-gated, minimal data only.
 */
class OverlayPublicController extends Controller
{
    public function __construct(
        protected OverlayService $overlay,
        protected MatchRoomService $rooms
    ) {}

    public function page(Request $request, TournamentMatch $match)
    {
        $config = $this->overlay->getOrCreateConfig($match);
        $token = $request->query('token');

        if (! $this->overlay->verifyToken($match, $token)) {
            abort(403, 'Invalid or missing overlay token');
        }

        $themeId = $request->query('theme', $config->theme_id);
        if (! in_array($themeId, BroadcastThemeRegistry::ids(), true)) {
            $themeId = $config->theme_id;
        }
        $panel = $request->query('panel', $config->active_panel);
        if (! array_key_exists($panel, BroadcastThemeRegistry::panels())) {
            $panel = $config->active_panel;
        }

        $theme = BroadcastThemeRegistry::get($themeId);

        return view('overlay-broadcast', [
            'match' => $match,
            'config' => $config,
            'theme' => $theme,
            'panel' => $panel,
            'token' => $config->public_token,
            'preview' => (bool) $request->boolean('preview'),
        ]);
    }

    public function data(Request $request, TournamentMatch $match)
    {
        $token = $request->query('token') ?? $request->header('X-Overlay-Token');
        if (! $this->overlay->verifyToken($match, $token)) {
            return response()->json(['error' => 'unauthorized'], 403);
        }

        $config = $this->overlay->getOrCreateConfig($match);
        $payload = $this->overlay->payload($match, $config);

        // Allow query overrides for theme/panel preview without mutating config
        if ($request->filled('theme') && in_array($request->query('theme'), BroadcastThemeRegistry::ids(), true)) {
            $payload['broadcast']['theme'] = BroadcastThemeRegistry::get($request->query('theme'));
        }
        if ($request->filled('panel') && array_key_exists($request->query('panel'), BroadcastThemeRegistry::panels())) {
            $payload['broadcast']['panel'] = $request->query('panel');
        }

        $room = $this->rooms->getOrCreate($match->room_id);

        return response()->json([
            'data' => $payload,
            'version' => $room->version,
            'events' => $this->drainNewEvents($room, $request->query('last_event')),
            'latest_event_id' => $this->latestEventId($room),
        ]);
    }

    public function poll(Request $request, TournamentMatch $match)
    {
        return $this->data($request, $match);
    }

    protected function drainNewEvents($room, ?string $afterId): array
    {
        $queue = $room->event_queue ?? [];
        if (! $afterId) {
            return [];
        }
        $found = false;
        $out = [];
        foreach ($queue as $ev) {
            if ($found) {
                $out[] = $ev;
            }
            if (($ev['id'] ?? '') === $afterId) {
                $found = true;
            }
        }

        return $out;
    }

    protected function latestEventId($room): ?string
    {
        $queue = $room->event_queue ?? [];
        if (! count($queue)) {
            return null;
        }

        return $queue[count($queue) - 1]['id'] ?? null;
    }
}
