<?php

namespace App\Services;

use App\Models\MatchRoom;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\Overlay\OverlayService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tournament knockout engine — generates a full bracket and advances winners.
 * Does NOT alter CricketScoringService lifecycle; only reacts after match completion.
 */
class KnockoutBracketService
{
    public const STATUS_WAITING = 'waiting';

    public const STATUS_READY = 'ready';

    public const STATUS_LIVE = 'live';

    public const STATUS_COMPLETED = 'completed';

    public function __construct(
        protected MatchRoomService $rooms,
        protected OverlayService $overlays,
        protected CricketScoringService $scoring
    ) {}

    public function isKnockoutTournament(Tournament $tournament): bool
    {
        return $tournament->isKnockout();
    }

    /**
     * Create all 15 R16→Final matches for a 16-team knockout.
     *
     * @param  list<string>  $teams  Exactly 16 team names
     */
    public function generateRoundOf16Bracket(
        Tournament $tournament,
        array $teams,
        ?Carbon $r16ScheduledAt = null
    ): Tournament {
        $teams = array_values(array_map(fn ($t) => trim((string) $t), $teams));
        if (count($teams) !== 16) {
            throw new \InvalidArgumentException('Knockout Round of 16 requires exactly 16 teams.');
        }
        foreach ($teams as $i => $name) {
            if ($name === '') {
                throw new \InvalidArgumentException('Team '.($i + 1).' name is required.');
            }
        }

        return DB::transaction(function () use ($tournament, $teams, $r16ScheduledAt) {
            // Clear prior bracket rows safely (self-FKs)
            $prior = $tournament->matches()->where('is_bracket', true);
            $prior->update([
                'next_match_id' => null,
                'source_match_a_id' => null,
                'source_match_b_id' => null,
            ]);
            $tournament->matches()->where('is_bracket', true)->delete();

            $scheduled = $r16ScheduledAt ?? now();
            $order = 0;
            $byCode = [];

            for ($i = 1; $i <= 8; $i++) {
                $a = $teams[($i - 1) * 2];
                $b = $teams[($i - 1) * 2 + 1];
                $byCode["R16-{$i}"] = $this->createBracketMatch($tournament, [
                    'match_no' => ++$order,
                    'bracket_order' => $order,
                    'bracket_code' => "R16-{$i}",
                    'round_code' => 'R16',
                    'round' => 'Round of 16',
                    'team_a_name' => $a,
                    'team_b_name' => $b,
                    'status' => self::STATUS_READY,
                    'scheduled_at' => $scheduled,
                ]);
            }

            for ($i = 1; $i <= 4; $i++) {
                $byCode["QF-{$i}"] = $this->createBracketMatch($tournament, [
                    'match_no' => ++$order,
                    'bracket_order' => $order,
                    'bracket_code' => "QF-{$i}",
                    'round_code' => 'QF',
                    'round' => 'Quarter-finals',
                    // TBD placeholder until winners advance (team columns are NOT NULL)
                    'team_a_name' => 'TBD',
                    'team_b_name' => 'TBD',
                    'status' => self::STATUS_WAITING,
                    'scheduled_at' => null,
                    'source_match_a_id' => $byCode['R16-'.(($i - 1) * 2 + 1)]->id,
                    'source_match_b_id' => $byCode['R16-'.(($i - 1) * 2 + 2)]->id,
                ]);
            }

            for ($i = 1; $i <= 2; $i++) {
                $byCode["SF-{$i}"] = $this->createBracketMatch($tournament, [
                    'match_no' => ++$order,
                    'bracket_order' => $order,
                    'bracket_code' => "SF-{$i}",
                    'round_code' => 'SF',
                    'round' => 'Semi-finals',
                    'team_a_name' => 'TBD',
                    'team_b_name' => 'TBD',
                    'status' => self::STATUS_WAITING,
                    'scheduled_at' => null,
                    'source_match_a_id' => $byCode['QF-'.(($i - 1) * 2 + 1)]->id,
                    'source_match_b_id' => $byCode['QF-'.(($i - 1) * 2 + 2)]->id,
                ]);
            }

            $byCode['FINAL'] = $this->createBracketMatch($tournament, [
                'match_no' => ++$order,
                'bracket_order' => $order,
                'bracket_code' => 'FINAL',
                'round_code' => 'F',
                'round' => 'Final',
                'team_a_name' => 'TBD',
                'team_b_name' => 'TBD',
                'status' => self::STATUS_WAITING,
                'scheduled_at' => null,
                'source_match_a_id' => $byCode['SF-1']->id,
                'source_match_b_id' => $byCode['SF-2']->id,
            ]);

            $links = [
                'R16-1' => ['QF-1', 'A'], 'R16-2' => ['QF-1', 'B'],
                'R16-3' => ['QF-2', 'A'], 'R16-4' => ['QF-2', 'B'],
                'R16-5' => ['QF-3', 'A'], 'R16-6' => ['QF-3', 'B'],
                'R16-7' => ['QF-4', 'A'], 'R16-8' => ['QF-4', 'B'],
                'QF-1' => ['SF-1', 'A'], 'QF-2' => ['SF-1', 'B'],
                'QF-3' => ['SF-2', 'A'], 'QF-4' => ['SF-2', 'B'],
                'SF-1' => ['FINAL', 'A'], 'SF-2' => ['FINAL', 'B'],
            ];
            foreach ($links as $from => [$to, $slot]) {
                $byCode[$from]->next_match_id = $byCode[$to]->id;
                $byCode[$from]->next_slot = $slot;
                $byCode[$from]->save();
            }

            $tournament->bracket_size = 16;
            $tournament->type = 'Knockout Tournament';
            $tournament->champion_name = null;
            $tournament->completed_at = null;
            $tournament->status = 'active';
            $tournament->save();

            foreach ($byCode as $match) {
                $this->seedMatchRoom($tournament, $match->fresh(['sourceMatchA', 'sourceMatchB']));
            }

            $this->syncTournamentOverlayKnockout($tournament->fresh());

            return $tournament->fresh(['matches']);
        });
    }

    protected function createBracketMatch(Tournament $tournament, array $attrs): TournamentMatch
    {
        $matchNo = (int) $attrs['match_no'];
        $roomId = TournamentMatch::makeRoomId($tournament->id, $matchNo);

        $match = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'match_no' => $matchNo,
            'round' => $attrs['round'],
            'overs' => $attrs['overs'] ?? 20,
            'scheduled_at' => $attrs['scheduled_at'] ?? null,
            'team_a_name' => ($attrs['team_a_name'] ?? '') !== '' ? $attrs['team_a_name'] : 'TBD',
            'team_b_name' => ($attrs['team_b_name'] ?? '') !== '' ? $attrs['team_b_name'] : 'TBD',
            'room_id' => $roomId,
            'status' => $attrs['status'],
            'result' => null,
            'is_bracket' => true,
            'bracket_code' => $attrs['bracket_code'],
            'round_code' => $attrs['round_code'],
            'bracket_order' => $attrs['bracket_order'] ?? $matchNo,
            'next_match_id' => null,
            'next_slot' => null,
            'source_match_a_id' => $attrs['source_match_a_id'] ?? null,
            'source_match_b_id' => $attrs['source_match_b_id'] ?? null,
            'winner_name' => null,
            'winner_side' => null,
        ]);

        try {
            $this->overlays->getOrCreateConfig($match);
        } catch (\Throwable) {
            // Overlay config is optional for bracket generation
        }

        return $match;
    }

    public function seedMatchRoom(Tournament $tournament, TournamentMatch $match): void
    {
        $room = $this->rooms->getOrCreate($match->room_id);
        $teamA = $match->displayTeamA();
        $teamB = $match->displayTeamB();

        $state = $room->state ?? CricketScoringService::defaultState();
        $state = $this->scoring->updateMeta($state, [
            'matchTitle' => $tournament->name.' · '.($match->bracket_code ?: $match->round),
            'matchNo' => (string) $match->match_no,
            'totalOvers' => (int) ($match->overs ?: 20),
            'themeId' => $tournament->theme_id,
            'teamA' => [
                'name' => $teamA,
                'shortName' => mb_strtoupper(mb_substr($teamA, 0, 3)),
            ],
            'teamB' => [
                'name' => $teamB,
                'shortName' => mb_strtoupper(mb_substr($teamB, 0, 3)),
            ],
        ]);
        $state['tournamentId'] = $tournament->id;
        $state['tournamentMatchId'] = $match->id;

        $this->rooms->saveStateQuiet($room, $state);
    }

    /**
     * Called after a MatchRoom is saved. Advances bracket when completed with a winner.
     */
    public function onMatchRoomCompleted(MatchRoom $room): void
    {
        $state = $room->state ?? [];
        if (($state['matchStatus'] ?? '') !== 'completed') {
            // Still sync live status onto bracket rows
            $match = TournamentMatch::query()
                ->where('room_id', $room->room_id)
                ->where('is_bracket', true)
                ->first();
            if ($match) {
                $this->syncMatchRowFromState($match, $state);
            }

            return;
        }

        $match = TournamentMatch::query()
            ->where('room_id', $room->room_id)
            ->where('is_bracket', true)
            ->first();
        if (! $match) {
            return;
        }

        $tournament = $match->tournament;
        if (! $tournament || ! $this->isKnockoutTournament($tournament)) {
            $this->syncMatchRowFromState($match, $state);

            return;
        }

        DB::transaction(function () use ($match, $state, $tournament) {
            $match = TournamentMatch::query()->lockForUpdate()->find($match->id);
            if (! $match) {
                return;
            }

            $this->syncMatchRowFromState($match, $state);
            $winner = $this->deriveWinner($match, $state);
            if (! $winner) {
                return;
            }

            if ($match->winner_name === $winner['name'] && $match->status === self::STATUS_COMPLETED) {
                $this->advanceWinner($match, $winner['name'], $winner['side']);

                return;
            }

            $match->winner_name = $winner['name'];
            $match->winner_side = $winner['side'];
            $match->status = self::STATUS_COMPLETED;
            $match->result = $state['result'] ?? $match->result;
            $match->save();

            $this->advanceWinner($match, $winner['name'], $winner['side']);
            $this->syncTournamentOverlayKnockout($tournament->fresh());
        });
    }

    protected function syncMatchRowFromState(TournamentMatch $match, array $state): void
    {
        $status = $state['matchStatus'] ?? '';
        $dirty = false;

        if ($status === 'completed') {
            if ($match->status !== self::STATUS_COMPLETED) {
                $match->status = self::STATUS_COMPLETED;
                $dirty = true;
            }
            if (! empty($state['result']) && $match->result !== $state['result']) {
                $match->result = $state['result'];
                $dirty = true;
            }
        } elseif ($status === 'live' || $status === 'innings_break') {
            if (in_array($match->status, [self::STATUS_READY, self::STATUS_WAITING, 'scheduled'], true)) {
                $match->status = self::STATUS_LIVE;
                $dirty = true;
            }
        }

        if ($dirty) {
            $match->save();
        }
    }

    /**
     * @return array{name:string,side:string}|null
     */
    public function deriveWinner(TournamentMatch $match, array $state): ?array
    {
        $result = trim((string) ($state['result'] ?? ''));
        if ($result === '') {
            return null;
        }
        $lower = strtolower($result);
        if (
            str_contains($lower, 'tied')
            || str_contains($lower, 'no result')
            || str_contains($lower, 'abandoned')
            || str_contains($lower, 'rain')
        ) {
            return null;
        }

        $nameA = trim((string) ($state['teamA']['name'] ?? $match->team_a_name ?? ''));
        $nameB = trim((string) ($state['teamB']['name'] ?? $match->team_b_name ?? ''));

        $side = strtoupper((string) ($state['lastEvent']['winner'] ?? ''));
        if ($side === 'A' && $nameA !== '') {
            return ['name' => $nameA, 'side' => 'A'];
        }
        if ($side === 'B' && $nameB !== '') {
            return ['name' => $nameB, 'side' => 'B'];
        }

        if ($nameA !== '' && (str_starts_with($result, $nameA) || str_contains($result, $nameA.' won'))) {
            return ['name' => $nameA, 'side' => 'A'];
        }
        if ($nameB !== '' && (str_starts_with($result, $nameB) || str_contains($result, $nameB.' won'))) {
            return ['name' => $nameB, 'side' => 'B'];
        }

        return null;
    }

    public function advanceWinner(TournamentMatch $match, string $winnerName, ?string $winnerSide = null): void
    {
        if (! $match->next_match_id || ! in_array($match->next_slot, ['A', 'B'], true)) {
            if ($match->round_code === 'F' || $match->bracket_code === 'FINAL') {
                $this->completeTournament($match, $winnerName);
            }

            return;
        }

        $next = TournamentMatch::query()->lockForUpdate()->find($match->next_match_id);
        if (! $next) {
            return;
        }

        $slot = $match->next_slot;
        $field = $slot === 'B' ? 'team_b_name' : 'team_a_name';

        if ($next->{$field} === $winnerName) {
            $this->refreshReadiness($next);
            $next->save();

            return;
        }

        if ($next->{$field} !== null && $next->{$field} !== '' && $next->{$field} !== $winnerName) {
            if (! $this->isPlaceholderName((string) $next->{$field})) {
                return;
            }
        }

        $next->{$field} = $winnerName;
        $this->refreshReadiness($next);
        $next->save();

        $tournament = $next->tournament;
        if ($tournament) {
            $this->seedMatchRoom($tournament, $next->fresh(['sourceMatchA', 'sourceMatchB']));
        }
    }

    protected function isPlaceholderName(string $name): bool
    {
        $n = strtolower(trim($name));

        return $n === '' || $n === 'tbd' || str_starts_with($n, 'winner ');
    }

    public function refreshReadiness(TournamentMatch $match): void
    {
        if ($match->status === self::STATUS_COMPLETED || $match->status === self::STATUS_LIVE) {
            return;
        }

        $a = trim((string) ($match->team_a_name ?? ''));
        $b = trim((string) ($match->team_b_name ?? ''));
        $hasA = $a !== '' && ! $this->isPlaceholderName($a);
        $hasB = $b !== '' && ! $this->isPlaceholderName($b);

        $match->status = ($hasA && $hasB) ? self::STATUS_READY : self::STATUS_WAITING;
    }

    protected function completeTournament(TournamentMatch $final, string $champion): void
    {
        $tournament = $final->tournament;
        if (! $tournament) {
            return;
        }
        if ($tournament->status === 'completed' && $tournament->champion_name === $champion) {
            return;
        }
        $tournament->champion_name = $champion;
        $tournament->status = 'completed';
        $tournament->completed_at = now();
        $tournament->save();
    }

    /**
     * Mirror bracket into tournament-level room knockout overlay JSON.
     */
    public function syncTournamentOverlayKnockout(Tournament $tournament): void
    {
        $matches = $tournament->matches()->where('is_bracket', true)->orderBy('bracket_order')->get();
        if ($matches->isEmpty()) {
            return;
        }

        $overlayMatches = [];
        foreach ($matches as $m) {
            $slot = match ($m->round_code) {
                'R16' => 'r16_'.preg_replace('/\D+/', '', (string) $m->bracket_code),
                'QF' => 'qf'.preg_replace('/\D+/', '', (string) $m->bracket_code),
                'SF' => 'sf'.preg_replace('/\D+/', '', (string) $m->bracket_code),
                'F' => 'final',
                default => strtolower((string) $m->bracket_code),
            };
            $overlayMatches[] = [
                'slot' => $slot,
                'round' => $m->round_code ?: 'QF',
                'label' => $m->bracket_code ?: $m->round,
                'side' => $m->round_code === 'F' ? 'C' : 'L',
                'group' => '',
                'teamA' => $m->displayTeamA(),
                'teamB' => $m->displayTeamB(),
                'time' => optional($m->scheduled_at)->format('g:i A') ?: '',
            ];
        }

        $ko = [
            'title' => 'KNOCKOUT ROUND',
            'format' => 16,
            'custom' => true,
            'roundDates' => ['r16' => '', 'qf' => '', 'sf' => '', 'final' => ''],
            'matches' => $overlayMatches,
        ];

        try {
            $room = $this->rooms->getOrCreate($tournament->room_id);
            $state = $room->state ?? CricketScoringService::defaultState();
            $state['knockout'] = $ko;
            $state['matchTitle'] = $tournament->name;
            $this->rooms->saveStateQuiet($room, $state);
        } catch (\Throwable) {
            // non-fatal
        }
    }

    /**
     * Simulate completing a bracket match with a known winner (for tests / admin tooling).
     */
    public function completeBracketMatchWithWinner(TournamentMatch $match, string $winnerSide): void
    {
        $winnerSide = strtoupper($winnerSide) === 'B' ? 'B' : 'A';
        $room = $this->rooms->getOrCreate($match->room_id);
        $state = $room->state ?? CricketScoringService::defaultState();
        $nameA = $match->team_a_name ?: ($state['teamA']['name'] ?? 'Team A');
        $nameB = $match->team_b_name ?: ($state['teamB']['name'] ?? 'Team B');
        $winnerName = $winnerSide === 'B' ? $nameB : $nameA;

        $state['teamA']['name'] = $nameA;
        $state['teamB']['name'] = $nameB;
        $state['result'] = $winnerName.' won by 10 runs';
        $state['matchStatus'] = 'completed';
        $state['lastEvent'] = ['type' => 'result', 'winner' => $winnerSide];
        $state['tournamentMatchId'] = $match->id;

        $this->rooms->saveState($room, $state);
    }
}
