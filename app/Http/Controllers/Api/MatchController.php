<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TournamentMatch;
use App\Services\AnimationPayloadBuilder;
use App\Services\CricketScoringService;
use App\Services\MatchRoomService;
use App\Services\SavedTeamService;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function __construct(
        protected MatchRoomService $rooms,
        protected CricketScoringService $scoring,
        protected AnimationPayloadBuilder $payloads,
        protected SavedTeamService $savedTeams
    ) {}

    public function show(string $room)
    {
        $match = $this->rooms->getOrCreate($room);

        return response()->json([
            'room' => $match->room_id,
            'version' => $match->version,
            'state' => $match->state,
            'events' => $match->event_queue ?? [],
        ]);
    }

    public function stream(Request $request, string $room)
    {
        $match = $this->rooms->getOrCreate($room);
        $lastVersion = (int) $request->query('since', 0);
        $lastEventId = $request->query('last_event');

        return response()->stream(function () use ($room, $lastVersion, $lastEventId) {
            $since = $lastVersion;
            $eventCursor = $lastEventId;
            $loops = 0;

            while ($loops < 120) { // ~60s at 500ms
                $match = $this->rooms->getOrCreate($room);
                $version = (int) $match->version;

                if ($version > $since) {
                    $payload = json_encode([
                        'room' => $match->room_id,
                        'version' => $version,
                        'state' => $match->state,
                    ]);
                    echo "event: state\n";
                    echo "data: {$payload}\n\n";
                    $since = $version;
                    if (function_exists('ob_flush')) {
                        @ob_flush();
                    }
                    @flush();
                }

                $events = $this->rooms->drainEvents($match, $eventCursor);
                foreach ($events as $ev) {
                    $payload = json_encode($ev);
                    echo "event: animation\n";
                    echo "data: {$payload}\n\n";
                    $eventCursor = $ev['id'] ?? $eventCursor;
                    if (function_exists('ob_flush')) {
                        @ob_flush();
                    }
                    @flush();
                }

                // Heartbeat
                echo ": ping\n\n";
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                @flush();

                usleep(500000);
                $loops++;

                if (connection_aborted()) {
                    break;
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function patch(Request $request, string $room)
    {
        $match = $this->rooms->getOrCreate($room);
        $state = $this->scoring->updateMeta($match->state, $request->all());
        $this->persistTeamsFromState($state);
        $match = $this->rooms->saveState($match, $state);

        return $this->ok($match);
    }

    public function uploadLogo(Request $request, string $room)
    {
        $request->validate([
            'logo' => 'required|file|mimes:jpg,jpeg,png,webp,gif,svg|max:4096',
            'slot' => 'nullable|in:organizer,streamer,teamA,teamB,player',
        ]);

        $slot = $request->input('slot', 'organizer');
        $safeRoom = preg_replace('/[^a-zA-Z0-9_-]/', '', $room) ?: 'match';
        $ext = strtolower($request->file('logo')->getClientOriginalExtension() ?: 'png');
        $filename = $slot.'-'.uniqid('', true).'.'.$ext;
        $dir = public_path('uploads/logos/'.$safeRoom);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $request->file('logo')->move($dir, $filename);
        $url = '/uploads/logos/'.$safeRoom.'/'.$filename;

        $match = $this->rooms->getOrCreate($room);
        $patch = match ($slot) {
            'teamA' => ['teamA' => ['logo' => $url]],
            'teamB' => ['teamB' => ['logo' => $url]],
            'streamer' => ['streamerLogo' => $url],
            'player' => ['playerAvatar' => $url],
            default => ['organizerLogo' => $url],
        };
        // Keep existing team names when patching logo only
        if (isset($patch['teamA'])) {
            $patch['teamA']['name'] = $match->state['teamA']['name'] ?? 'Team A';
        }
        if (isset($patch['teamB'])) {
            $patch['teamB']['name'] = $match->state['teamB']['name'] ?? 'Team B';
        }
        $state = $this->scoring->updateMeta($match->state, $patch);
        $match = $this->rooms->saveState($match, $state);

        return response()->json([
            'room' => $match->room_id,
            'version' => $match->version,
            'state' => $match->state,
            'url' => $url,
            'slot' => $slot,
            'events' => $match->event_queue ?? [],
        ]);
    }

    public function setPlayers(Request $request, string $room)
    {
        $match = $this->rooms->getOrCreate($room);
        $state = $this->scoring->setPlayers($match->state, $request->all());
        $match = $this->rooms->saveState($match, $state);
        $le = is_array($state['lastEvent'] ?? null) ? $state['lastEvent'] : [];
        if (($le['type'] ?? '') === 'error') {
            return response()->json([
                'error' => $le['message'] ?? 'Invalid player selection',
                'room' => $match->room_id,
                'version' => $match->version,
                'state' => $match->state,
                'events' => $match->event_queue ?? [],
            ], 422);
        }

        return $this->ok($match);
    }

    public function setBatting(Request $request, string $room)
    {
        $match = $this->rooms->getOrCreate($room);
        $team = $request->input('team', 'A');
        $state = $this->scoring->setBattingTeam($match->state, $team);
        $match = $this->rooms->saveState($match, $state);

        return $this->ok($match);
    }

    public function action(Request $request, string $room)
    {
        $match = $this->rooms->getOrCreate($room);
        $type = $request->input('type');
        $state = $match->state;
        $animation = null;

        if (! in_array($type, ['undo', 'reset'], true)) {
            $blocked = $this->waitingBracketResponse($room);
            if ($blocked) {
                return $blocked;
            }
            $state = $this->scoring->pushHistory($state);
        }

        $autoGfx = ($state['autoGraphics'] ?? true) !== false && ($state['autoGraphics'] ?? true) !== '0';

        switch ($type) {
            case 'dot':
            case 'run':
                $runs = (int) $request->input('runs', $type === 'dot' ? 0 : 1);
                $state = $this->scoring->scoreRuns($state, $runs);
                if ($autoGfx && $runs === 4) {
                    $animation = ['animation' => 'four', 'payload' => array_merge($this->payloads->build('four', $state), $this->batterPayload($state))];
                } elseif ($autoGfx && $runs === 6) {
                    $animation = ['animation' => 'six', 'payload' => array_merge($this->payloads->build('six', $state), $this->batterPayload($state))];
                }
                break;

            case 'wicket':
                $dismissal = $request->input('dismissal', 'OUT');
                $state = $this->scoring->scoreWicket($state, $dismissal);
                if ($autoGfx) {
                    $animation = [
                        'animation' => 'wicket',
                        'payload' => array_merge($this->payloads->build('wicket', $state), [
                            'player' => $state['lastEvent']['player'] ?? '',
                            'dismissal' => $dismissal,
                        ]),
                    ];
                }
                break;

            case 'wide':
                $state = $this->scoring->scoreWide($state, (int) $request->input('extra', 0));
                break;

            case 'noball':
                $state = $this->scoring->scoreNoBall($state, (int) $request->input('runs', 0));
                break;

            case 'bye':
                $state = $this->scoring->scoreBye($state, (int) $request->input('runs', 1), false);
                break;

            case 'legbye':
                $state = $this->scoring->scoreBye($state, (int) $request->input('runs', 1), true);
                break;

            case 'penalty':
                $state = $this->scoring->scorePenalty($state, (int) $request->input('runs', 5));
                break;

            case 'rotate_strike':
                $state = $this->scoring->rotateStrikeManual($state);
                break;

            case 'end_innings':
                $state = $this->scoring->endInnings($state);
                $breakExtra = is_array($state['lastEvent'] ?? null) ? $state['lastEvent'] : [];
                // After match complete, default overlay = Match Summary (batting + bowling both teams).
                $animType = ! empty($breakExtra['matchComplete']) ? 'match_summary' : 'innings_break';
                $animation = [
                    'animation' => $animType,
                    'payload' => $this->payloads->build($animType, $state, $breakExtra),
                ];
                break;

            case 'start_innings':
            case 'start_second_innings':
                $state = $this->scoring->startSecondInnings($state);
                break;

            case 'walkover':
                $state = $this->scoring->walkover(
                    $state,
                    $request->input('winner', $state['battingTeam'] === 'A' ? 'B' : 'A'),
                    $request->input('reason', 'Walkover / abandoned')
                );
                $animation = ['animation' => 'winner', 'payload' => []];
                break;

            case 'undo':
                $state = $this->scoring->undo($state);
                break;

            case 'reset':
                $state = $this->scoring->reset($state);
                break;

            case 'add_squad':
                $state = $this->scoring->addSquadPlayer(
                    $state,
                    $request->input('team', 'A'),
                    (string) $request->input('name', ''),
                    (string) $request->input('avatar', '')
                );
                $this->persistSide($state, (string) $request->input('team', 'A'));
                break;

            case 'remove_squad':
                $state = $this->scoring->removeSquadPlayer(
                    $state,
                    $request->input('team', 'A'),
                    (string) $request->input('playerId', '')
                );
                $this->persistSide($state, (string) $request->input('team', 'A'), true);
                break;

            case 'update_squad':
                $state = $this->scoring->updateSquadPlayer(
                    $state,
                    $request->input('team', 'A'),
                    (string) $request->input('playerId', ''),
                    (array) $request->input('player', $request->except(['type', 'team', 'playerId']))
                );
                $this->persistSide($state, (string) $request->input('team', 'A'));
                break;

            case 'save_team':
                // Explicit save of current side into the team library.
                $side = strtoupper((string) $request->input('team', 'A')) === 'B' ? 'B' : 'A';
                $key = $side === 'B' ? 'teamB' : 'teamA';
                $saved = $this->savedTeams->upsertFromMatchSide(
                    $state[$key] ?? [],
                    isset($state['tournamentId']) ? (int) $state['tournamentId'] : null,
                    true
                );
                if (! $saved) {
                    return response()->json(['error' => 'Team name and squad required to save'], 422);
                }
                $state['lastEvent'] = [
                    'type' => 'team_saved',
                    'teamId' => $saved->id,
                    'teamName' => $saved->name,
                ];
                break;

            case 'import_team':
                $side = strtoupper((string) $request->input('team', 'A')) === 'B' ? 'B' : 'A';
                $teamId = (int) $request->input('teamId', 0);
                $saved = $this->savedTeams->find($teamId);
                if (! $saved) {
                    return response()->json(['error' => 'Saved team not found'], 404);
                }
                $state = $this->savedTeams->applyToMatchState($state, $side, $saved);
                $state['lastEvent'] = [
                    'type' => 'team_imported',
                    'team' => $side,
                    'teamId' => $saved->id,
                    'teamName' => $saved->name,
                ];
                break;

            case 'commentary':
                $state = $this->scoring->addCommentary($state, (string) $request->input('text', ''));
                break;

            default:
                return response()->json(['error' => 'Unknown action'], 422);
        }

        // Natural innings/match end triggered inside scoring (overs / all out).
        // Target reach is pending confirm — no winner animation until operator ends.
        $le = is_array($state['lastEvent'] ?? null) ? $state['lastEvent'] : [];
        if ($animation === null && ($le['type'] ?? '') === 'innings_break') {
            $animType = ! empty($le['matchComplete']) ? 'match_summary' : 'innings_break';
            $animation = [
                'animation' => $animType,
                'payload' => $this->payloads->build($animType, $state, $le),
            ];
        }

        $match = $this->rooms->saveState($match, $state, $animation);

        return $this->ok($match);
    }

    protected function persistSide(array $state, string $side, bool $forceEmpty = false): void
    {
        $key = strtoupper($side) === 'B' ? 'teamB' : 'teamA';
        try {
            $this->savedTeams->upsertFromMatchSide(
                $state[$key] ?? [],
                isset($state['tournamentId']) ? (int) $state['tournamentId'] : null,
                $forceEmpty
            );
        } catch (\Throwable $e) {
            // Library persist must never break live scoring.
            report($e);
        }
    }

    protected function persistTeamsFromState(array $state): void
    {
        $this->persistSide($state, 'A');
        $this->persistSide($state, 'B');
    }

    public function animate(Request $request, string $room)
    {
        $match = $this->rooms->getOrCreate($room);
        $type = (string) $request->input('animation', 'custom');
        $extra = (array) $request->input('payload', []);
        $state = $match->state ?? [];

        // Infer tournament id from room pattern t{id}-m{n}-… when missing.
        if (empty($state['tournamentId']) && preg_match('/^t(\d+)-m/i', $room, $m)) {
            $state['tournamentId'] = (int) $m[1];
            $match = $this->rooms->saveState($match, $state);
            $state = $match->state ?? $state;
        }

        // Team A / Team B squad buttons
        if ($type === 'team_lineup' && ! empty($extra['team'])) {
            $extra = array_merge($extra, $this->payloads->lineupPayload($state, (string) $extra['team']));
        }

        $payload = $this->payloads->build($type, $state, $extra);

        $animation = [
            'animation' => $type,
            'payload' => $payload,
        ];
        $match = $this->rooms->saveState($match, $state, $animation);

        return $this->ok($match);
    }

    public function poll(Request $request, string $room)
    {
        $match = $this->rooms->getOrCreate($room);
        $since = (int) $request->query('since', 0);
        $lastEvent = $request->query('last_event');
        $queue = $match->event_queue ?? [];
        $latestEventId = count($queue) ? ($queue[count($queue) - 1]['id'] ?? null) : null;

        $events = [];
        if ($lastEvent) {
            $events = $this->rooms->drainEvents($match, $lastEvent);
        } elseif ($match->version > $since && count($queue)) {
            $events = [end($queue)];
        }

        return response()->json([
            'room' => $match->room_id,
            'version' => $match->version,
            'changed' => $match->version > $since,
            'state' => $match->state,
            'events' => $events,
            'latest_event_id' => $latestEventId,
        ]);
    }

    protected function waitingBracketResponse(string $roomId)
    {
        $tm = TournamentMatch::query()
            ->where('room_id', $roomId)
            ->where('is_bracket', true)
            ->first();
        if ($tm && $tm->isWaiting()) {
            return response()->json([
                'error' => 'This bracket match is WAITING for prior winners. It cannot be started yet.',
                'status' => 'waiting',
            ], 422);
        }

        return null;
    }

    protected function ok($match)
    {
        return response()->json([
            'room' => $match->room_id,
            'version' => $match->version,
            'state' => $match->state,
            'events' => $match->event_queue ?? [],
        ]);
    }

    protected function batterPayload(array $state): array
    {
        $key = ($state['battingTeam'] ?? 'A') === 'B' ? 'teamB' : 'teamA';
        $team = $state[$key];
        $striker = null;
        foreach ($team['batsmen'] ?? [] as $b) {
            if (($b['id'] ?? null) === ($team['strikerId'] ?? null)) {
                $striker = $b;
                break;
            }
        }

        return [
            'player' => $striker['name'] ?? '',
            'runs' => $state['lastEvent']['runs'] ?? null,
        ];
    }
}
