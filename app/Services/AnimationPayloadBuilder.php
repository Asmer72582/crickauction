<?php

namespace App\Services;

use App\Models\MatchRoom;
use App\Models\TournamentMatch;

/**
 * Builds fully dynamic overlay animation payloads from live match state.
 */
class AnimationPayloadBuilder
{
    public function build(string $animation, array $state, array $extra = []): array
    {
        $type = strtolower(trim($animation));
        $base = $this->fromState($type, $state, $extra);
        $clean = [];
        foreach ($extra as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            if (is_array($v) && $v === []) {
                continue;
            }
            $clean[$k] = $v;
        }

        $merged = array_merge($base, $clean);

        // Re-apply totals so client scope extras can't leave stale team split data.
        if ($type === 'boundaries_counter') {
            $merged = array_merge($merged, $this->boundariesAggregate($state, $extra));
        }

        return $merged;
    }

    protected function fromState(string $type, array $state, array $extra = []): array
    {
        $teamA = $state['teamA'] ?? [];
        $teamB = $state['teamB'] ?? [];
        $battingKey = ($state['battingTeam'] ?? 'A') === 'B' ? 'B' : 'A';
        $batting = $battingKey === 'B' ? $teamB : $teamA;
        $bowling = $battingKey === 'B' ? $teamA : $teamB;

        $squadNames = function (array $team): array {
            $fromSquad = array_values(array_filter(array_map(
                fn ($p) => is_array($p) ? ($p['name'] ?? '') : (string) $p,
                $team['squad'] ?? []
            )));
            $fromBat = array_values(array_filter(array_map(
                fn ($p) => $p['name'] ?? '',
                $team['batsmen'] ?? []
            )));

            return array_values(array_unique(array_merge($fromSquad, $fromBat)));
        };

        $striker = $this->findById($batting['batsmen'] ?? [], $batting['strikerId'] ?? null);
        $nonStriker = $this->findById($batting['batsmen'] ?? [], $batting['nonStrikerId'] ?? null);

        return match ($type) {
            'team_vs_team' => [
                'teamAName' => $teamA['name'] ?? 'Team A',
                'teamBName' => $teamB['name'] ?? 'Team B',
                'teamALogo' => $teamA['logo'] ?? '',
                'teamBLogo' => $teamB['logo'] ?? '',
                'teamAColor' => $teamA['primaryColor'] ?? '#e63946',
                'teamBColor' => $teamB['primaryColor'] ?? '#1d8cf8',
                'matchNo' => $state['matchNo'] ?? '1',
                'matchTitle' => $state['matchTitle'] ?? '',
                'tournament' => $state['matchTitle'] ?? '',
                'organizerLogo' => $state['organizerLogo'] ?? '',
                'streamerLogo' => $state['streamerLogo'] ?? '',
            ],
            'knockout_round' => $this->knockoutPayload($state),
            'toss' => [
                'winnerName' => $state['toss']['winner'] ?? '',
                'decision' => $this->formatTossDecision($state['toss']['decision'] ?? ''),
                'teamAName' => $teamA['name'] ?? 'Team A',
                'teamBName' => $teamB['name'] ?? 'Team B',
                'teamALogo' => $teamA['logo'] ?? '',
                'teamBLogo' => $teamB['logo'] ?? '',
                'matchNo' => $state['matchNo'] ?? '1',
                'tournament' => $state['matchTitle'] ?? '',
                'text' => $state['toss']['text'] ?? '',
                'organizerLogo' => $state['organizerLogo'] ?? '',
                'streamerLogo' => $state['streamerLogo'] ?? '',
            ],
            'boundaries_counter' => array_merge(
                $this->boundariesAggregate($state, $extra),
                [
                    'organizerLogo' => $state['organizerLogo'] ?? '',
                    'streamerLogo' => $state['streamerLogo'] ?? '',
                    'tournament' => $state['matchTitle'] ?? '',
                    'matchTitle' => $state['matchTitle'] ?? '',
                    'matchNo' => $state['matchNo'] ?? '1',
                ]
            ),
            'blackboard' => [
                'text' => $state['customMessage'] ?: ($state['result'] ?: ($state['matchTitle'] ?? 'Black Board')),
                'title' => 'NOTICE BOARD',
                'organizerLogo' => $state['organizerLogo'] ?? '',
                'streamerLogo' => $state['streamerLogo'] ?? '',
            ],
            'both_squads' => [
                'matchNo' => $state['matchNo'] ?? '1',
                'teamA' => [
                    'name' => $teamA['name'] ?? 'Team A',
                    'logo' => $teamA['logo'] ?? '',
                    'color' => $teamA['primaryColor'] ?? '#e63946',
                    'players' => array_slice($squadNames($teamA), 0, 11),
                    'avatars' => array_slice(array_map(fn ($p) => [
                        'name' => $p['name'] ?? '',
                        'avatar' => $p['avatar'] ?? '',
                    ], $teamA['squad'] ?? []), 0, 11),
                ],
                'teamB' => [
                    'name' => $teamB['name'] ?? 'Team B',
                    'logo' => $teamB['logo'] ?? '',
                    'color' => $teamB['primaryColor'] ?? '#1d8cf8',
                    'players' => array_slice($squadNames($teamB), 0, 11),
                    'avatars' => array_slice(array_map(fn ($p) => [
                        'name' => $p['name'] ?? '',
                        'avatar' => $p['avatar'] ?? '',
                    ], $teamB['squad'] ?? []), 0, 11),
                ],
                'organizerLogo' => $state['organizerLogo'] ?? '',
                'streamerLogo' => $state['streamerLogo'] ?? '',
                'playerAvatar' => $state['playerAvatar'] ?? '',
            ],
            'team_lineup' => $this->lineupPayload($state, null),
            'batting_summary' => $this->battingSummaryPayload($state, $extra['team'] ?? null),
            'bowling_summary' => $this->bowlingSummaryPayload($state, $extra['team'] ?? null),
            'field_position' => [
                'positions' => $this->enrichFieldPositions($state, $bowling),
                'zone' => $state['fieldZone'] ?? 'Standard',
                'battingTeam' => $batting['name'] ?? '',
                'bowlingTeam' => $bowling['name'] ?? '',
                'bowler' => ($this->findById($bowling['bowlers'] ?? [], $bowling['currentBowlerId'] ?? null) ?? [])['name'] ?? '',
                'striker' => $striker['name'] ?? '',
                'nonStriker' => $nonStriker['name'] ?? '',
                'bowlingSquad' => array_values(array_unique(array_filter(array_merge(
                    array_map(fn ($p) => is_array($p) ? ($p['name'] ?? '') : (string) $p, $bowling['squad'] ?? []),
                    array_map(fn ($p) => $p['name'] ?? '', $bowling['bowlers'] ?? [])
                )))),
                'organizerLogo' => $state['organizerLogo'] ?? '',
                'streamerLogo' => $state['streamerLogo'] ?? '',
            ],
            'partnership' => [
                'player1' => $striker['name'] ?? 'Batter 1',
                'player2' => $nonStriker['name'] ?? 'Batter 2',
                'runs' => (int) ($striker['runs'] ?? 0) + (int) ($nonStriker['runs'] ?? 0),
                'balls' => (int) ($striker['balls'] ?? 0) + (int) ($nonStriker['balls'] ?? 0),
                'playerAvatar' => $state['playerAvatar'] ?? '',
                'organizerLogo' => $state['organizerLogo'] ?? '',
                'streamerLogo' => $state['streamerLogo'] ?? '',
            ],
            'instant_show' => $this->instantShowPayload($state, $batting, $bowling, $striker, $nonStriker),
            'player_card' => $this->playerCardPayload($state, $extra),
            'tournament_name' => [
                'title' => $state['matchTitle'] ?? 'Tournament',
                'tournament' => $state['matchTitle'] ?? '',
                'initials' => strtoupper(substr((string) ($state['matchTitle'] ?? 'CT'), 0, 2)),
                'organizerLogo' => $state['organizerLogo'] ?? '',
                'streamerLogo' => $state['streamerLogo'] ?? '',
            ],
            'commentator' => [
                'commentator' => $extra['commentator'] ?? ($state['commentator'] ?? 'Commentator'),
                'text' => $extra['text'] ?? ($extra['commentator'] ?? ($state['commentator'] ?? 'Commentator')),
                'organizerLogo' => $state['organizerLogo'] ?? '',
                'streamerLogo' => $state['streamerLogo'] ?? '',
            ],
            'message', 'custom', 'sponsor' => [
                'text' => $extra['text'] ?? ($state['customMessage'] ?? ''),
                'organizerLogo' => $state['organizerLogo'] ?? '',
                'streamerLogo' => $state['streamerLogo'] ?? '',
            ],
            default => [
                'organizerLogo' => $state['organizerLogo'] ?? '',
                'streamerLogo' => $state['streamerLogo'] ?? '',
                'playerAvatar' => $state['playerAvatar'] ?? '',
            ],
        };
    }

    /**
     * Resolve a player card from squad settings + live match history.
     * Prefers explicit playerId / playerName; falls back to role (striker/nonstriker/bowler).
     */
    public function playerCardPayload(array $state, array $extra = []): array
    {
        $scoring = new CricketScoringService;
        $teamA = $state['teamA'] ?? [];
        $teamB = $state['teamB'] ?? [];
        $battingKey = ($state['battingTeam'] ?? 'A') === 'B' ? 'B' : 'A';
        $batting = $battingKey === 'B' ? $teamB : $teamA;
        $bowling = $battingKey === 'B' ? $teamA : $teamB;

        $role = strtolower(trim((string) ($extra['role'] ?? '')));
        $mode = strtolower(trim((string) ($extra['mode'] ?? '')));
        $playerId = trim((string) ($extra['playerId'] ?? ''));
        $playerName = trim((string) ($extra['playerName'] ?? $extra['name'] ?? ''));
        $teamHint = strtoupper(trim((string) ($extra['team'] ?? '')));
        if ($teamHint !== 'A' && $teamHint !== 'B') {
            $teamHint = '';
        }

        $live = null;
        $cardRole = 'batsman';
        $teamName = $batting['name'] ?? 'Team';
        $teamKey = $battingKey;

        if ($playerId !== '' || $playerName !== '') {
            $squad = $scoring->findSquadPlayer($state, $playerId ?: null, $playerName ?: null, $teamHint ?: null);
            if ($squad) {
                $playerName = $squad['name'];
                $teamName = $squad['teamName'];
                $teamKey = $squad['teamKey'];
                $teamSide = $teamKey === 'B' ? $teamB : $teamA;
                $liveBat = $this->findByName($teamSide['batsmen'] ?? [], $playerName);
                $liveBowl = $this->findByName($teamSide['bowlers'] ?? [], $playerName);
                $preferBowl = ($mode === 'history' && ($squad['role'] ?? '') === 'bowler')
                    || $role === 'bowler'
                    || ($liveBowl && ! $liveBat);
                if ($preferBowl && $liveBowl) {
                    $live = $liveBowl;
                    $cardRole = 'bowler';
                } elseif ($liveBat) {
                    $live = $liveBat;
                    $cardRole = 'batsman';
                } elseif ($liveBowl) {
                    $live = $liveBowl;
                    $cardRole = 'bowler';
                } else {
                    $cardRole = in_array($squad['role'] ?? '', ['bowler'], true) ? 'bowler' : 'batsman';
                }

                return $this->packPlayerCard($state, $squad, $live, $cardRole, $teamName, $mode ?: ($live ? 'live' : 'history'));
            }
        }

        // Live roles from current innings
        $striker = $this->findById($batting['batsmen'] ?? [], $batting['strikerId'] ?? null);
        $nonStriker = $this->findById($batting['batsmen'] ?? [], $batting['nonStrikerId'] ?? null);
        $bowler = $this->findById($bowling['bowlers'] ?? [], $bowling['currentBowlerId'] ?? null);

        if ($role === 'bowler') {
            $live = $bowler;
            $cardRole = 'bowler';
            $teamName = $bowling['name'] ?? 'Team';
            $teamKey = $battingKey === 'B' ? 'A' : 'B';
            $playerName = $bowler['name'] ?? 'Bowler';
        } elseif ($role === 'nonstriker' || $role === 'non_striker') {
            $live = $nonStriker;
            $playerName = $nonStriker['name'] ?? 'Player';
        } else {
            $live = $striker;
            $playerName = $striker['name'] ?? 'Player';
            $role = $role ?: 'striker';
        }

        $squad = $scoring->findSquadPlayer($state, null, $playerName, $teamKey)
            ?: [
                'name' => $playerName,
                'avatar' => $live['avatar'] ?? ($state['playerAvatar'] ?? ''),
                'role' => $cardRole,
                'battingStyle' => '',
                'bowlingStyle' => '',
                'history' => ['matches' => 0, 'runs' => 0, 'wickets' => 0, 'average' => '', 'best' => '', 'strikeRate' => ''],
                'teamName' => $teamName,
                'teamKey' => $teamKey,
            ];

        return $this->packPlayerCard($state, $squad, $live, $cardRole, $teamName, $mode ?: 'live');
    }

    protected function packPlayerCard(array $state, array $squad, ?array $live, string $cardRole, string $teamName, string $mode): array
    {
        $history = is_array($squad['history'] ?? null) ? $squad['history'] : [];
        $avatar = (string) ($squad['avatar'] ?? '');
        if ($avatar === '') {
            $avatar = (string) ($live['avatar'] ?? ($state['playerAvatar'] ?? ''));
        }

        $isBowler = $cardRole === 'bowler';
        $runs = (int) ($live['runs'] ?? 0);
        $balls = (int) ($live['balls'] ?? 0);
        $fours = (int) ($live['fours'] ?? 0);
        $sixes = (int) ($live['sixes'] ?? 0);
        $wickets = (int) ($live['wickets'] ?? 0);
        $dots = (int) ($live['dots'] ?? 0);
        $overs = $balls > 0 ? (intdiv($balls, 6).'.'.($balls % 6)) : '0.0';
        $economy = $balls > 0 ? number_format(($runs / $balls) * 6, 2, '.', '') : '0.00';
        $sr = $this->calcSr($runs, $balls);
        $hasLive = is_array($live) && (
            $balls > 0 || $runs > 0 || $wickets > 0 || ! empty($live['id'])
        );
        $showHistory = $mode === 'history' || ! $hasLive;

        if ($showHistory) {
            $avg = (string) ($history['average'] ?? '');
            // Career / squad history card
            return [
                'mode' => 'history',
                'role' => (($squad['role'] ?? '') === 'bowler') ? 'bowler' : 'batsman',
                'playingRole' => $squad['role'] ?? 'batsman',
                'teamName' => $teamName,
                'playerName' => $squad['name'] ?? 'Player',
                'battingStyle' => $squad['battingStyle'] ?? '',
                'bowlingStyle' => $squad['bowlingStyle'] ?? '',
                'avatar' => $avatar,
                'playerAvatar' => $avatar,
                'runs' => (int) ($history['runs'] ?? 0),
                'balls' => $hasLive ? $balls : 0,
                'fours' => $hasLive ? $fours : 0,
                'sixes' => $hasLive ? $sixes : 0,
                'wickets' => (int) ($history['wickets'] ?? 0),
                'overs' => $hasLive && $isBowler ? $overs : '—',
                'economy' => $avg !== '' ? $avg : '—',
                'dots' => $hasLive ? $dots : 0,
                'strikeRate' => (string) (($history['strikeRate'] ?? '') !== '' ? $history['strikeRate'] : '—'),
                'sr' => (string) (($history['strikeRate'] ?? '') !== '' ? $history['strikeRate'] : '—'),
                'matches' => (int) ($history['matches'] ?? 0),
                'average' => $avg,
                'best' => (string) ($history['best'] ?? ''),
                'thisMatch' => $hasLive ? [
                    'role' => $isBowler ? 'bowler' : 'batsman',
                    'runs' => $runs,
                    'balls' => $balls,
                    'fours' => $fours,
                    'sixes' => $sixes,
                    'wickets' => $wickets,
                    'overs' => $overs,
                    'economy' => $economy,
                    'dots' => $dots,
                    'sr' => $sr,
                ] : null,
                'history' => $history,
                'organizerLogo' => $state['organizerLogo'] ?? '',
                'streamerLogo' => $state['streamerLogo'] ?? '',
            ];
        }

        return [
            'mode' => 'live',
            'role' => $isBowler ? 'bowler' : 'batsman',
            'playingRole' => $squad['role'] ?? ($isBowler ? 'bowler' : 'batsman'),
            'teamName' => $teamName,
            'playerName' => $squad['name'] ?? ($live['name'] ?? 'Player'),
            'battingStyle' => $squad['battingStyle'] ?? '',
            'bowlingStyle' => $squad['bowlingStyle'] ?? '',
            'avatar' => $avatar,
            'playerAvatar' => $avatar,
            'runs' => $runs,
            'balls' => $balls,
            'fours' => $fours,
            'sixes' => $sixes,
            'wickets' => $wickets,
            'overs' => $overs,
            'economy' => $economy,
            'dots' => $dots,
            'strikeRate' => $sr,
            'sr' => $sr,
            'matches' => (int) ($history['matches'] ?? 0),
            'average' => (string) ($history['average'] ?? ''),
            'best' => (string) ($history['best'] ?? ''),
            'history' => $history,
            'organizerLogo' => $state['organizerLogo'] ?? '',
            'streamerLogo' => $state['streamerLogo'] ?? '',
        ];
    }

    protected function findByName(array $list, string $name): ?array
    {
        foreach ($list as $p) {
            if (strcasecmp($p['name'] ?? '', $name) === 0) {
                return $p;
            }
        }

        return null;
    }

    public function lineupPayload(array $state, ?string $teamKey = null): array
    {
        $key = $teamKey === 'B' || $teamKey === 'A' ? $teamKey : (($state['battingTeam'] ?? 'A') === 'B' ? 'B' : 'A');
        $team = $key === 'B' ? ($state['teamB'] ?? []) : ($state['teamA'] ?? []);
        $players = [];
        foreach ($team['squad'] ?? [] as $p) {
            if (! empty($p['name'])) {
                $players[] = $p['name'];
            }
        }
        if (! count($players)) {
            foreach ($team['batsmen'] ?? [] as $b) {
                if (! empty($b['name'])) {
                    $players[] = $b['name'];
                }
            }
        }

        return [
            'teamName' => $team['name'] ?? ('Team '.$key),
            'logo' => $team['logo'] ?? '',
            'color' => $team['primaryColor'] ?? '',
            'matchNo' => $state['matchNo'] ?? '1',
            'round' => $state['round'] ?? '',
            'players' => array_slice(array_values(array_unique($players)), 0, 11),
            'avatars' => array_slice(array_map(fn ($p) => [
                'name' => $p['name'] ?? '',
                'avatar' => $p['avatar'] ?? '',
            ], $team['squad'] ?? []), 0, 11),
            'organizerLogo' => $state['organizerLogo'] ?? '',
            'streamerLogo' => $state['streamerLogo'] ?? '',
            'playerAvatar' => $state['playerAvatar'] ?? '',
        ];
    }

    /**
     * Full squad batting card — yet-to-bat players included.
     */
    public function battingSummaryPayload(array $state, ?string $teamKey = null): array
    {
        $key = $teamKey === 'B' || $teamKey === 'A'
            ? $teamKey
            : (($state['battingTeam'] ?? 'A') === 'B' ? 'B' : 'A');
        $batSide = $key === 'B' ? ($state['teamB'] ?? []) : ($state['teamA'] ?? []);
        $other = $key === 'B' ? ($state['teamA'] ?? []) : ($state['teamB'] ?? []);

        $byName = [];
        foreach ($batSide['batsmen'] ?? [] as $b) {
            $n = strtolower(trim((string) ($b['name'] ?? '')));
            if ($n !== '') {
                $byName[$n] = $b;
            }
        }

        $ordered = [];
        foreach ($batSide['squad'] ?? [] as $p) {
            $name = trim((string) ($p['name'] ?? ''));
            if ($name !== '') {
                $ordered[] = $name;
            }
        }
        $ordered = array_slice(array_values(array_unique($ordered)), 0, 11);

        if (! count($ordered)) {
            foreach ($batSide['batsmen'] ?? [] as $b) {
                $name = trim((string) ($b['name'] ?? ''));
                if ($name !== '') {
                    $ordered[] = $name;
                }
            }
        }

        foreach ($batSide['batsmen'] ?? [] as $b) {
            $name = trim((string) ($b['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $exists = false;
            foreach ($ordered as $existing) {
                if (strcasecmp($existing, $name) === 0) {
                    $exists = true;
                    break;
                }
            }
            if (! $exists) {
                $ordered[] = $name;
            }
        }

        $players = [];
        foreach (array_slice($ordered, 0, 11) as $name) {
            $live = $byName[strtolower($name)] ?? null;
            if (! $live) {
                $players[] = [
                    'name' => $name,
                    'runs' => 0,
                    'balls' => 0,
                    'out' => false,
                    'yetToBat' => true,
                    'dismissal' => 'Yet to bat',
                ];
                continue;
            }
            $out = ! empty($live['out']);
            $players[] = [
                'name' => $live['name'] ?? $name,
                'runs' => (int) ($live['runs'] ?? 0),
                'balls' => (int) ($live['balls'] ?? 0),
                'out' => $out,
                'yetToBat' => false,
                'dismissal' => $out ? ($live['dismissal'] ?? 'out') : 'Not out',
            ];
        }

        return [
            'teamName' => $batSide['name'] ?? ('Team '.$key),
            'teamLogo' => $batSide['logo'] ?? '',
            'matchNo' => $state['matchNo'] ?? '1',
            'round' => $state['round'] ?? '',
            'overs' => $batSide['overs'] ?? '0.0',
            'score' => (int) ($batSide['score'] ?? 0),
            'wickets' => (int) ($batSide['wickets'] ?? 0),
            'extras' => $batSide['extras'] ?? [],
            'badgeLeft' => strtoupper(substr((string) ($batSide['shortName'] ?? $batSide['name'] ?? 'T'), 0, 2)),
            'badgeRight' => strtoupper(substr((string) ($other['shortName'] ?? $other['name'] ?? 'T'), 0, 2)),
            'organizerLogo' => $state['organizerLogo'] ?? '',
            'streamerLogo' => $state['streamerLogo'] ?? '',
            'players' => $players,
            'team' => $key,
        ];
    }

    public function bowlingSummaryPayload(array $state, ?string $teamKey = null): array
    {
        $key = $teamKey === 'B' || $teamKey === 'A'
            ? $teamKey
            : (($state['battingTeam'] ?? 'A') === 'B' ? 'A' : 'B'); // bowling side default = non-batting
        // If explicit team passed, that team is the bowling card
        if ($teamKey === 'A' || $teamKey === 'B') {
            $key = $teamKey;
        } else {
            $key = ($state['battingTeam'] ?? 'A') === 'B' ? 'A' : 'B';
        }

        $bowlSide = $key === 'B' ? ($state['teamB'] ?? []) : ($state['teamA'] ?? []);
        $oppBat = $key === 'B' ? ($state['teamA'] ?? []) : ($state['teamB'] ?? []);

        $bowlers = [];
        foreach ($bowlSide['bowlers'] ?? [] as $b) {
            $balls = (int) ($b['balls'] ?? 0);
            $runs = (int) ($b['runs'] ?? 0);
            $overs = intdiv($balls, 6).'.'.($balls % 6);
            $econ = $balls > 0 ? number_format($runs / ($balls / 6), 2) : '—';
            $bowlers[] = [
                'name' => $b['name'] ?? '',
                'overs' => $overs,
                'runs' => $runs,
                'wickets' => (int) ($b['wickets'] ?? 0),
                'dots' => (int) ($b['dots'] ?? 0),
                'econ' => $econ,
            ];
        }

        $fow = [];
        foreach ($oppBat['batsmen'] ?? [] as $i => $b) {
            if (! empty($b['out'])) {
                $fow[] = $b['fowScore'] ?? ($i + 1);
            }
        }

        return [
            'teamName' => $bowlSide['name'] ?? ('Team '.$key),
            'teamLogo' => $bowlSide['logo'] ?? '',
            'matchNo' => $state['matchNo'] ?? '1',
            'round' => $state['round'] ?? '',
            'overs' => $oppBat['overs'] ?? '0.0',
            'score' => (int) ($oppBat['score'] ?? 0),
            'wickets' => (int) ($oppBat['wickets'] ?? 0),
            'extras' => $oppBat['extras'] ?? [],
            'badgeLeft' => strtoupper(substr((string) ($bowlSide['shortName'] ?? $bowlSide['name'] ?? 'T'), 0, 2)),
            'badgeRight' => strtoupper(substr((string) ($oppBat['shortName'] ?? $oppBat['name'] ?? 'T'), 0, 2)),
            'organizerLogo' => $state['organizerLogo'] ?? '',
            'streamerLogo' => $state['streamerLogo'] ?? '',
            'fow' => $fow,
            'bowlers' => $bowlers,
            'team' => $key,
        ];
    }

    /**
     * Merge admin-set field spots with live bowler / squad names when player blank.
     */
    protected function enrichFieldPositions(array $state, array $bowling): array
    {
        $positions = $state['fieldPositions'] ?? CricketScoringService::defaultFieldPositions();
        $bowlerName = ($this->findById($bowling['bowlers'] ?? [], $bowling['currentBowlerId'] ?? null) ?? [])['name'] ?? '';
        $squad = array_values(array_filter(array_map(
            fn ($p) => is_array($p) ? trim((string) ($p['name'] ?? '')) : trim((string) $p),
            $bowling['squad'] ?? []
        )));
        $used = [];
        $squadIdx = 0;

        foreach ($positions as &$pos) {
            $id = strtolower((string) ($pos['id'] ?? ''));
            $player = trim((string) ($pos['player'] ?? ''));
            if ($player !== '') {
                $used[$player] = true;
                continue;
            }
            if ($id === 'bowler' && $bowlerName !== '') {
                $pos['player'] = $bowlerName;
                $used[$bowlerName] = true;
                continue;
            }
            while ($squadIdx < count($squad) && isset($used[$squad[$squadIdx]])) {
                $squadIdx++;
            }
            if ($squadIdx < count($squad)) {
                $pos['player'] = $squad[$squadIdx];
                $used[$squad[$squadIdx]] = true;
                $squadIdx++;
            }
        }
        unset($pos);

        return $positions;
    }

    protected function instantShowPayload(array $state, array $batting, array $bowling, ?array $striker, ?array $nonStriker): array
    {
        $players = [];
        $seen = [];
        $pushBat = function (?array $p, string $role) use (&$players, &$seen, $batting) {
            $name = trim((string) ($p['name'] ?? ''));
            if ($name === '' || isset($seen[strtolower($name)])) {
                return;
            }
            $seen[strtolower($name)] = true;
            $players[] = [
                'name' => $name,
                'role' => $role,
                'team' => $batting['name'] ?? '',
                'runs' => (int) ($p['runs'] ?? 0),
                'balls' => (int) ($p['balls'] ?? 0),
                'fours' => (int) ($p['fours'] ?? 0),
                'sixes' => (int) ($p['sixes'] ?? 0),
                'sr' => $this->calcSr($p['runs'] ?? 0, $p['balls'] ?? 0),
            ];
        };

        $pushBat($striker, 'STRIKER');
        $pushBat($nonStriker, 'NON-STRIKER');
        foreach ($batting['batsmen'] ?? [] as $p) {
            $role = ! empty($p['out']) ? 'OUT' : 'BATTED';
            $pushBat($p, $role);
        }

        $bowler = $this->findById($bowling['bowlers'] ?? [], $bowling['currentBowlerId'] ?? null);
        if ($bowler && ! empty($bowler['name'])) {
            $balls = (int) ($bowler['balls'] ?? 0);
            $runs = (int) ($bowler['runs'] ?? 0);
            $overs = intdiv($balls, 6).'.'.($balls % 6);
            $econ = $balls > 0 ? number_format(($runs / $balls) * 6, 2, '.', '') : '0.00';
            $players[] = [
                'name' => $bowler['name'],
                'role' => 'BOWLER',
                'team' => $bowling['name'] ?? '',
                'runs' => $runs,
                'wickets' => (int) ($bowler['wickets'] ?? 0),
                'overs' => $overs,
                'econ' => $econ,
                'dots' => (int) ($bowler['dots'] ?? 0),
            ];
        }

        $milestones = [];
        foreach ($batting['batsmen'] ?? [] as $p) {
            $r = (int) ($p['runs'] ?? 0);
            if ($r < 50) {
                continue;
            }
            $milestones[] = [
                'runs' => $r >= 100 ? 100 : 50,
                'player' => $p['name'] ?? '',
                'detail' => $r.' ('.((int) ($p['balls'] ?? 0)).')',
            ];
        }
        usort($milestones, fn ($a, $b) => ($b['runs'] <=> $a['runs']));

        [$mf, $ms] = $this->sumMatchBoundaries($state);
        [$tf, $ts] = $this->sumTournamentBoundaries($state);

        return [
            'subtitle' => trim(($state['matchTitle'] ?? 'LIVE').' · MATCH '.($state['matchNo'] ?? '1')),
            'players' => $players,
            'boundariesMatch' => ['fours' => $mf, 'sixes' => $ms],
            'boundariesTournament' => ['fours' => $tf, 'sixes' => $ts],
            'milestones' => $milestones,
            'partnership' => [
                'player1' => $striker['name'] ?? 'Batter 1',
                'player2' => $nonStriker['name'] ?? 'Batter 2',
                'runs' => (int) ($striker['runs'] ?? 0) + (int) ($nonStriker['runs'] ?? 0),
                'balls' => (int) ($striker['balls'] ?? 0) + (int) ($nonStriker['balls'] ?? 0),
            ],
            'organizerLogo' => $state['organizerLogo'] ?? '',
            'streamerLogo' => $state['streamerLogo'] ?? '',
        ];
    }

    /**
     * Aggregate 4s/6s for this match or whole tournament (not per team).
     *
     * @return array{scope: string, label: string, title: string, fours: int, sixes: int, teamA: null, teamB: null, teamName: string}
     */
    protected function boundariesAggregate(array $state, array $extra = []): array
    {
        $scope = strtolower((string) ($extra['scope'] ?? $state['boundariesScope'] ?? 'tournament'));
        if ($scope !== 'match') {
            $scope = 'tournament';
        }

        if ($scope === 'match') {
            [$fours, $sixes] = $this->sumMatchBoundaries($state);
            $label = 'THIS MATCH';
        } else {
            [$fours, $sixes] = $this->sumTournamentBoundaries($state);
            $label = 'TOURNAMENT';
        }

        return [
            'scope' => $scope,
            'label' => $label,
            'title' => 'BOUNDARIES',
            'fours' => $fours,
            'sixes' => $sixes,
            // Explicitly clear legacy per-team payload so overlay never splits by team.
            'teamA' => null,
            'teamB' => null,
            'teamName' => $label,
        ];
    }

    /** @return array{0: int, 1: int} */
    protected function sumMatchBoundaries(array $state): array
    {
        $fours = 0;
        $sixes = 0;
        foreach (['teamA', 'teamB'] as $key) {
            foreach ($state[$key]['batsmen'] ?? [] as $b) {
                $fours += (int) ($b['fours'] ?? 0);
                $sixes += (int) ($b['sixes'] ?? 0);
            }
        }

        return [$fours, $sixes];
    }

    /** @return array{0: int, 1: int} */
    protected function sumTournamentBoundaries(array $state): array
    {
        $tournamentId = $state['tournamentId'] ?? null;
        if (! $tournamentId) {
            return $this->sumMatchBoundaries($state);
        }

        $roomIds = TournamentMatch::query()
            ->where('tournament_id', (int) $tournamentId)
            ->pluck('room_id')
            ->filter()
            ->values()
            ->all();

        if ($roomIds === []) {
            // Fallback: any saved room whose state shares this tournamentId.
            $roomIds = MatchRoom::query()
                ->get(['room_id', 'state'])
                ->filter(fn (MatchRoom $room) => (int) (($room->state['tournamentId'] ?? 0)) === (int) $tournamentId)
                ->pluck('room_id')
                ->all();
        }

        if ($roomIds === []) {
            return $this->sumMatchBoundaries($state);
        }

        $fours = 0;
        $sixes = 0;
        $rooms = MatchRoom::query()->whereIn('room_id', $roomIds)->get(['room_id', 'state']);
        foreach ($rooms as $room) {
            [$f, $s] = $this->sumMatchBoundaries(is_array($room->state) ? $room->state : []);
            $fours += $f;
            $sixes += $s;
        }

        return [$fours, $sixes];
    }

    protected function knockoutPayload(array $state): array
    {
        $ko = is_array($state['knockout'] ?? null) ? $state['knockout'] : [];
        $format = match ((int) ($ko['format'] ?? 8)) {
            4 => 4,
            16 => 16,
            default => 8,
        };
        $matches = [];
        foreach (($ko['matches'] ?? []) as $m) {
            if (! is_array($m) || empty($m['slot'])) {
                continue;
            }
            $matches[] = [
                'slot' => strtolower((string) $m['slot']),
                'round' => strtoupper((string) ($m['round'] ?? '')),
                'label' => trim((string) ($m['label'] ?? '')),
                'side' => (string) ($m['side'] ?? 'L'),
                'group' => (string) ($m['group'] ?? ''),
                'teamA' => trim((string) ($m['teamA'] ?? '')),
                'teamB' => trim((string) ($m['teamB'] ?? '')),
                'time' => trim((string) ($m['time'] ?? '')),
            ];
        }
        if ($matches === []) {
            $matches = CricketScoringService::defaultKnockout($format)['matches'];
        }
        $rd = is_array($ko['roundDates'] ?? null) ? $ko['roundDates'] : [];

        return [
            'title' => trim((string) ($ko['title'] ?? '')) ?: 'KNOCKOUT ROUND',
            'format' => $format,
            'custom' => (bool) ($ko['custom'] ?? false),
            'roundDates' => [
                'r64' => trim((string) ($rd['r64'] ?? '')),
                'r32' => trim((string) ($rd['r32'] ?? '')),
                'r16' => trim((string) ($rd['r16'] ?? '')),
                'qf' => trim((string) ($rd['qf'] ?? '')),
                'sf' => trim((string) ($rd['sf'] ?? '')),
                'final' => trim((string) ($rd['final'] ?? '')),
            ],
            'matches' => $matches,
            'tournament' => $state['matchTitle'] ?? '',
            'matchTitle' => $state['matchTitle'] ?? '',
            'matchNo' => $state['matchNo'] ?? '1',
            'organizerLogo' => $state['organizerLogo'] ?? '',
            'streamerLogo' => $state['streamerLogo'] ?? '',
        ];
    }

    protected function formatTossDecision(string $decision): string
    {
        $d = strtolower(trim($decision));
        if ($d === 'bat') {
            return 'elected to bat';
        }
        if ($d === 'bowl' || $d === 'ball') {
            return 'elected to bowl';
        }

        return $decision ?: 'won the toss';
    }

    protected function calcSr(mixed $runs, mixed $balls): string
    {
        $r = (int) $runs;
        $b = (int) $balls;
        if ($b <= 0) {
            return '0';
        }

        return number_format(($r / $b) * 100, 1, '.', '');
    }

    protected function findById(array $list, ?string $id): ?array
    {
        if (! $id) {
            return null;
        }
        foreach ($list as $p) {
            if (($p['id'] ?? null) === $id) {
                return $p;
            }
        }

        return null;
    }
}
