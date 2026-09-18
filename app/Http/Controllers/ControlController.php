<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\MatchRoomService;
use App\Services\OverlayThemeRegistry;
use Illuminate\Http\Request;

class ControlController extends Controller
{
    public function __construct(
        protected MatchRoomService $rooms
    ) {}

    public function index(Request $request)
    {
        $room = $request->query('room', 'match1');
        $tournament = Tournament::where('room_id', $room)->first();
        $theme = OverlayThemeRegistry::get($tournament?->theme_id ?? 'arena');

        return view('control', [
            'room' => $room,
            'tournament' => $tournament,
            'theme' => $theme,
            'wsUrl' => $this->websocketUrl($request),
        ]);
    }

    public function knockout(Request $request)
    {
        $room = $request->query('room', 'match1');
        $tournament = Tournament::where('room_id', $room)->first();
        if (! $tournament) {
            // Fall back: match room may belong to a tournament match row
            $match = TournamentMatch::where('room_id', $room)->first();
            $tournament = $match?->tournament;
        }
        $theme = OverlayThemeRegistry::get($tournament?->theme_id ?? 'arena');

        return view('control-knockout', [
            'room' => $room,
            'tournament' => $tournament,
            'theme' => $theme,
            'wsUrl' => $this->websocketUrl($request),
        ]);
    }

    public function overlay(Request $request)
    {
        $room = $request->query('room', 'match1');
        $match = $this->rooms->getOrCreate($room);
        $themeId = $match->state['themeId'] ?? null;

        if (! $themeId) {
            $tournament = Tournament::where('room_id', $room)->first();
            $themeId = $tournament?->theme_id ?? OverlayThemeRegistry::defaultId();
        }

        $themeId = OverlayThemeRegistry::resolveId($themeId);
        $theme = OverlayThemeRegistry::get($themeId);

        return view('overlay', [
            'room' => $room,
            'theme' => $theme,
            'wsUrl' => $this->websocketUrl($request),
        ]);
    }

    protected function websocketUrl(Request $request): string
    {
        $explicit = env('WS_HUB_URL');
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        $scheme = $request->secure() ? 'wss' : 'ws';
        $host = $request->getHost() ?: '127.0.0.1';
        $port = env('WS_HUB_PORT', '6001');

        return "{$scheme}://{$host}:{$port}";
    }
}
