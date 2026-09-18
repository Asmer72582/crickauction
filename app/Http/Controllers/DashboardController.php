<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\CricketScoringService;
use App\Services\KnockoutBracketService;
use App\Services\MatchRoomService;
use App\Services\OverlayThemeRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function __construct(
        protected MatchRoomService $rooms,
        protected CricketScoringService $scoring,
        protected KnockoutBracketService $knockout
    ) {}

    public function index()
    {
        $tournaments = Tournament::query()->visible()->withCount('matches')->latest()->get();
        $themes = OverlayThemeRegistry::all();

        return view('dashboard', [
            'tournaments' => $tournaments,
            'themes' => $themes,
        ]);
    }

    public function store(Request $request)
    {
        $isKnockout = stripos((string) $request->input('type'), 'knockout') !== false;

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'sport' => ['nullable', 'string', 'max:40'],
            'type' => ['nullable', 'string', 'max:60'],
            'theme_id' => ['required', Rule::in(OverlayThemeRegistry::ids())],
            'wickets' => ['nullable', 'integer', 'min:1', 'max:10'],
            'groups' => ['nullable', 'integer', 'min:1', 'max:16'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'assigned_to' => ['nullable', 'string', 'max:80'],
            'r16_scheduled_at' => ['nullable', 'date'],
        ];

        if ($isKnockout) {
            $rules['teams'] = ['required', 'array', 'size:16'];
            $rules['teams.*'] = ['required', 'string', 'max:80'];
        }

        $data = $request->validate($rules);

        $theme = OverlayThemeRegistry::get($data['theme_id']);
        $roomId = Tournament::makeRoomId($data['name']);

        $tournament = Tournament::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'sport' => $data['sport'] ?? 'Cricket',
            'type' => $data['type'] ?? 'League Tournament',
            'theme_id' => $theme['id'],
            'room_id' => $roomId,
            'wickets' => $data['wickets'] ?? 10,
            'groups' => $data['groups'] ?? 1,
            'starts_at' => $data['starts_at'] ?? now()->toDateString(),
            'ends_at' => $data['ends_at'] ?? now()->addDays(7)->toDateString(),
            'status' => 'active',
            'assigned_to' => $data['assigned_to'] ?? 'Operator',
            'theme_charge' => $theme['charges'],
        ]);

        $message = 'Tournament created. Now add matches.';

        if ($isKnockout) {
            $r16At = ! empty($data['r16_scheduled_at'])
                ? Carbon::parse($data['r16_scheduled_at'])
                : now();
            $tournament = $this->knockout->generateRoundOf16Bracket(
                $tournament,
                $data['teams'],
                $r16At
            );
            $message = 'Knockout tournament created with full 15-match bracket.';
        }

        $matchesUrl = url('/tournaments/'.$tournament->id.'/matches');

        if ($request->wantsJson()) {
            return response()->json([
                'tournament' => $tournament->loadCount('matches'),
                'matches_url' => $matchesUrl,
            ], 201);
        }

        return redirect($matchesUrl)->with('success', $message);
    }

    public function themes()
    {
        return response()->json([
            'themes' => array_values(OverlayThemeRegistry::all()),
        ]);
    }

    public function wallets(Request $request)
    {
        $health = app(\App\Services\RealtimePublisher::class)->health();

        return view('realtime', [
            'credentials' => \App\Support\WebSocketConfig::credentials($request),
            'health' => $health,
        ]);
    }
}
