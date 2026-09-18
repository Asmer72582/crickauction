<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\KnockoutBracketService;
use App\Services\MatchRoomService;
use App\Services\OverlayThemeRegistry;
use Illuminate\Http\Request;

class TournamentMatchController extends Controller
{
    public function __construct(
        protected MatchRoomService $rooms,
        protected KnockoutBracketService $knockout
    ) {}

    public function index(Tournament $tournament)
    {
        $tournament->load(['matches.sourceMatchA', 'matches.sourceMatchB']);
        $theme = OverlayThemeRegistry::get($tournament->theme_id);

        // Enrich matches with live score snapshot from MatchRoom state
        $cards = $tournament->matches->map(function (TournamentMatch $match) {
            $room = $this->rooms->getOrCreate($match->room_id);
            $state = $room->state ?? [];
            $teamA = $state['teamA'] ?? [];
            $teamB = $state['teamB'] ?? [];

            $result = $state['result'] ?? $match->result;
            $matchStatus = $state['matchStatus'] ?? '';

            if ($match->is_bracket && $matchStatus === 'completed') {
                // Ensure advancement runs even if saveState hook was skipped
                $this->knockout->onMatchRoomCompleted($room);
                $match = $match->fresh(['sourceMatchA', 'sourceMatchB']) ?? $match;
            } elseif ($matchStatus === 'completed' && $match->status !== 'completed') {
                if ($result) {
                    $match->result = $result;
                }
                $match->status = 'completed';
                $match->save();
            } elseif ($matchStatus !== 'completed' && $match->status === 'completed' && ! $match->is_bracket) {
                $match->status = 'live';
                $match->result = $result ?: null;
                $match->save();
            } elseif ($result && $match->result !== $result && $matchStatus === 'completed') {
                $match->result = $result;
                $match->save();
            } elseif ($match->is_bracket && in_array($matchStatus, ['live', 'innings_break'], true) && $match->status === 'ready') {
                $match->status = 'live';
                $match->save();
            }

            $displayA = $match->is_bracket
                ? ($match->team_a_name ?: $match->displayTeamA())
                : ($teamA['name'] ?? $match->team_a_name);
            $displayB = $match->is_bracket
                ? ($match->team_b_name ?: $match->displayTeamB())
                : ($teamB['name'] ?? $match->team_b_name);

            // Prefer live room names once both sides are real teams
            if ($match->is_bracket && $match->team_a_name && $match->team_b_name) {
                $displayA = $teamA['name'] ?? $match->team_a_name;
                $displayB = $teamB['name'] ?? $match->team_b_name;
            } elseif ($match->is_bracket) {
                $displayA = $match->displayTeamA();
                $displayB = $match->displayTeamB();
            }

            return [
                'match' => $match,
                'teamA' => [
                    'name' => $displayA,
                    'score' => $teamA['score'] ?? 0,
                    'wickets' => $teamA['wickets'] ?? 0,
                    'overs' => $teamA['overs'] ?? '0.0',
                ],
                'teamB' => [
                    'name' => $displayB,
                    'score' => $teamB['score'] ?? 0,
                    'wickets' => $teamB['wickets'] ?? 0,
                    'overs' => $teamB['overs'] ?? '0.0',
                ],
                'result' => $result,
                'overlay_url' => $match->overlayUrl(),
                'control_url' => $match->controlUrl(),
                'can_score' => $match->canOpenForScoring(),
            ];
        });

        return view('tournament-matches', [
            'tournament' => $tournament->fresh(),
            'theme' => $theme,
            'cards' => $cards,
            'isKnockout' => $tournament->isKnockout(),
            'savedTeams' => app(\App\Services\SavedTeamService::class)->list(null),
        ]);
    }

    public function store(Request $request, Tournament $tournament)
    {
        if ($tournament->isKnockout() && $tournament->matches()->where('is_bracket', true)->exists()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Knockout bracket matches are generated automatically. Do not create matches manually.',
                ], 422);
            }

            return redirect('/tournaments/'.$tournament->id.'/matches')
                ->with('success', 'Knockout bracket is automatic — open READY matches to score.');
        }

        $data = $request->validate([
            'team_a_name' => ['required', 'string', 'max:80'],
            'team_b_name' => ['required', 'string', 'max:80'],
            'round' => ['nullable', 'string', 'max:80'],
            'overs' => ['nullable', 'integer', 'min:1', 'max:50'],
            'scheduled_at' => ['nullable', 'date'],
            'match_no' => ['nullable', 'integer', 'min:1'],
        ]);

        $teamAName = trim($data['team_a_name']);
        $teamBName = trim($data['team_b_name']);
        if ($teamAName === '' || $teamBName === '') {
            return response()->json(['message' => 'Both team names are required.'], 422);
        }
        if (strcasecmp($teamAName, $teamBName) === 0) {
            return response()->json(['message' => 'Team A and Team B must be different.'], 422);
        }

        $nextNo = $data['match_no'] ?? (($tournament->matches()->max('match_no') ?? 0) + 1);
        $roomId = TournamentMatch::makeRoomId($tournament->id, $nextNo);

        $match = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'match_no' => $nextNo,
            'round' => $data['round'] ?? 'League',
            'overs' => $data['overs'] ?? 20,
            'scheduled_at' => $data['scheduled_at'] ?? now(),
            'team_a_name' => $teamAName,
            'team_b_name' => $teamBName,
            'room_id' => $roomId,
            'status' => 'scheduled',
            'result' => null,
        ]);

        // Seed live scoring room
        $room = $this->rooms->getOrCreate($roomId);
        $state = $room->state;
        $state['matchTitle'] = $tournament->name;
        $state['matchNo'] = (string) $match->match_no;
        $state['totalOvers'] = $match->overs;
        $state['themeId'] = $tournament->theme_id;
        $state['tournamentId'] = $tournament->id;
        $state['teamA']['name'] = $match->team_a_name;
        $state['teamA']['shortName'] = strtoupper(substr($match->team_a_name, 0, 3));
        $state['teamB']['name'] = $match->team_b_name;
        $state['teamB']['shortName'] = strtoupper(substr($match->team_b_name, 0, 3));

        // Find-or-create library teams (keep existing squads); apply onto match sides
        $state = $this->applyLibraryTeam($state, 'A', $teamAName, (int) $tournament->id);
        $state = $this->applyLibraryTeam($state, 'B', $teamBName, (int) $tournament->id);

        $this->rooms->saveState($room, $state);

        // Auto-create broadcast overlay config + public token
        app(\App\Services\Overlay\OverlayService::class)->getOrCreateConfig($match);

        if ($request->wantsJson()) {
            return response()->json([
                'match' => $match,
                'control_url' => $match->controlUrl(),
                'overlay_url' => $match->overlayUrl(),
                'broadcast_url' => url('/broadcast/match/'.$match->id),
                'matches_url' => url('/tournaments/'.$tournament->id.'/matches'),
            ], 201);
        }

        return redirect('/tournaments/'.$tournament->id.'/matches')
            ->with('success', 'Match #'.$match->match_no.' created.');
    }

    /**
     * Use existing saved team (with squad) if present; otherwise create a name-only library entry.
     */
    private function applyLibraryTeam(array $state, string $side, string $name, int $tournamentId): array
    {
        $teams = app(\App\Services\SavedTeamService::class);
        $key = \App\Models\SavedTeam::makeKey($name);
        $existing = $key !== ''
            ? \App\Models\SavedTeam::query()->where('name_key', $key)->first()
            : null;

        if ($existing) {
            return $teams->applyToMatchState($state, $side, $existing);
        }

        $created = $teams->upsertExplicit([
            'name' => $name,
            'players' => [],
        ], $tournamentId);

        return $teams->applyToMatchState($state, $side, $created);
    }
}
