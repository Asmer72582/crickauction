<?php

namespace App\Services;

/**
 * Full cricket ball-by-ball scoring engine.
 * Maintains batsmen, bowlers, overs, extras, strike, this-over, target.
 */
class CricketScoringService
{
    public static function defaultState(): array
    {
        return [
            'matchTitle' => 'Live Match',
            'matchNo' => '1',
            'totalOvers' => 20,
            'innings' => 1,
            'battingTeam' => 'A',
            // live | innings_break | completed — separate from innings number / result text
            'matchStatus' => 'live',
            'target' => null,
            'result' => '',
            // Set when chase target is reached; operator confirms before completing.
            'pendingMatchEnd' => null,
            'pendingMatchEndDismissed' => false,
            // Bowler who just finished an over — cannot bowl the next over.
            'lastBowlerId' => null,
            'visible' => true,
            'powerplay' => false,
            'displayMode' => 'scoreboard',
            'themeId' => 'arena',
            'tournamentId' => null,
            'organizerLogo' => '',
            'streamerLogo' => '',
            'playerAvatar' => '',
            'commentator' => '',
            'toss' => [
                'winner' => '',
                'decision' => '',
                'text' => '',
            ],
            'knockout' => self::defaultKnockout(8),
            'autoGraphics' => true,
            'showLogo' => true,
            'showBoundariesCounter' => true,
            'boundariesScope' => 'tournament',
            'autoLoop' => false,
            'thisOver' => [],
            'thisOverBowlerRuns' => 0,
            'lastEvent' => null,
            'lastOut' => null,
            'ballLog' => [],
            'history' => [],
            'commentary' => [],
            'customMessage' => '',
            'fieldZone' => 'Standard',
            'fieldPositions' => self::defaultFieldPositions(),
            'teamA' => self::defaultTeam('Team A', 'TMA', '#e63946'),
            'teamB' => self::defaultTeam('Team B', 'TMB', '#1d8cf8'),
        ];
    }

    public static function defaultFieldPositions(): array
    {
        // Kept inside the oval so labels can point inward without clipping.
        return [
            ['id' => 'keeper', 'label' => 'WK', 'x' => 50, 'y' => 74],
            ['id' => 'slip', 'label' => 'Slip', 'x' => 58, 'y' => 68],
            ['id' => 'gully', 'label' => 'Gully', 'x' => 64, 'y' => 62],
            ['id' => 'point', 'label' => 'Point', 'x' => 70, 'y' => 50],
            ['id' => 'cover', 'label' => 'Cover', 'x' => 66, 'y' => 38],
            ['id' => 'mid-off', 'label' => 'Mid Off', 'x' => 58, 'y' => 28],
            ['id' => 'mid-on', 'label' => 'Mid On', 'x' => 42, 'y' => 28],
            ['id' => 'midwicket', 'label' => 'Mid Wicket', 'x' => 34, 'y' => 38],
            ['id' => 'square-leg', 'label' => 'Square Leg', 'x' => 30, 'y' => 50],
            ['id' => 'fine-leg', 'label' => 'Fine Leg', 'x' => 34, 'y' => 68],
            ['id' => 'third-man', 'label' => 'Third Man', 'x' => 70, 'y' => 68],
            ['id' => 'bowler', 'label' => 'Bowler', 'x' => 50, 'y' => 20],
        ];
    }

    public static function defaultTeam(string $name, string $short, string $color): array
    {
        return [
            'name' => $name,
            'shortName' => $short,
            'primaryColor' => $color,
            'secondaryColor' => '',
            'logo' => '',
            'score' => 0,
            'wickets' => 0,
            'balls' => 0,
            'overs' => '0.0',
            'extras' => ['wd' => 0, 'nb' => 0, 'b' => 0, 'lb' => 0, 'pen' => 0],
            'batsmen' => [],
            'bowlers' => [],
            'squad' => [],
            'strikerId' => null,
            'nonStrikerId' => null,
            'currentBowlerId' => null,
            // Overlay-compat string fields (derived)
            'batsman1' => '',
            'batsman2' => '',
            'bowler' => '',
        ];
    }

    public function ensureDerived(array $state): array
    {
        foreach (['teamA', 'teamB'] as $key) {
            $state[$key] = $this->deriveTeamStrings($state[$key]);
            $state[$key]['overs'] = $this->ballsToOvers($state[$key]['balls'] ?? 0);
            if (! empty($state[$key]['squad']) && is_array($state[$key]['squad'])) {
                $state[$key]['squad'] = $this->sanitizeSquad($state[$key]['squad']);
            }
        }

        $state = $this->normalizeMatchStatus($state);

        // Target applies during innings break (pending chase) and 2nd innings.
        $inChasePhase = (int) ($state['innings'] ?? 1) >= 2
            || ($state['matchStatus'] ?? '') === 'innings_break';
        if ($inChasePhase) {
            if ($state['target'] === null || $state['target'] === '') {
                // During break the side that just batted is still battingTeam.
                // During chase the bowling side holds the first-innings total.
                if (($state['matchStatus'] ?? '') === 'innings_break') {
                    $firstKey = $this->battingKey($state);
                } else {
                    $firstKey = $this->bowlingKey($state);
                }
                $state['target'] = ((int) ($state[$firstKey]['score'] ?? 0)) + 1;
            } else {
                $state['target'] = (int) $state['target'];
            }
        } elseif (($state['matchStatus'] ?? '') !== 'completed') {
            $state['target'] = null;
        }

        if (! isset($state['knockout']) || ! is_array($state['knockout'])) {
            $state['knockout'] = self::defaultKnockout(8);
        } else {
            $state['knockout'] = $this->sanitizeKnockout($state['knockout']);
        }

        return $state;
    }

    /**
     * Single source of truth for match lifecycle phase.
     * A non-empty result string alone does NOT complete the match.
     */
    public function normalizeMatchStatus(array $state): array
    {
        $status = (string) ($state['matchStatus'] ?? '');
        if (! in_array($status, ['live', 'innings_break', 'completed'], true)) {
            $state['matchStatus'] = 'live';
        }

        return $state;
    }

    public function isMatchCompleted(array $state): bool
    {
        $state = $this->normalizeMatchStatus($state);

        return ($state['matchStatus'] ?? '') === 'completed';
    }

    public function isInningsBreak(array $state): bool
    {
        $state = $this->normalizeMatchStatus($state);

        return ($state['matchStatus'] ?? '') === 'innings_break';
    }

    protected function canScore(array $state): bool
    {
        $status = $state['matchStatus'] ?? 'live';
        if ($status !== 'live') {
            return false;
        }
        if ($this->requiresToss($state)) {
            return false;
        }

        return true;
    }

    /** Toss must be set before any play begins. */
    public function isTossComplete(array $state): bool
    {
        $winner = trim((string) ($state['toss']['winner'] ?? ''));
        $decision = strtolower(trim((string) ($state['toss']['decision'] ?? '')));

        return $winner !== '' && in_array($decision, ['bat', 'bowl'], true);
    }

    public function requiresToss(array $state): bool
    {
        if (($state['matchStatus'] ?? '') === 'completed') {
            return false;
        }
        if ($this->isTossComplete($state)) {
            return false;
        }
        // Only gate before any legal deliveries have been bowled.
        $a = (int) ($state['teamA']['balls'] ?? 0);
        $b = (int) ($state['teamB']['balls'] ?? 0);

        return $a === 0 && $b === 0;
    }

    /** Max overs a single bowler may bowl in this innings (limited-overs). */
    public function maxBowlerOvers(array $state): int
    {
        $total = max(1, (int) ($state['totalOvers'] ?? 20));

        return max(1, (int) floor($total / 5));
    }

    public function deriveTeamStrings(array $team): array
    {
        $striker = $this->findPlayer($team['batsmen'] ?? [], $team['strikerId'] ?? null);
        $nonStriker = $this->findPlayer($team['batsmen'] ?? [], $team['nonStrikerId'] ?? null);
        $bowler = $this->findPlayer($team['bowlers'] ?? [], $team['currentBowlerId'] ?? null);

        // When viewing bowling side's bowler on overlay, current bowler is on bowling team.
        // Overlay uses batting team's batsman1/2 and bowling team's bowler field via engine.
        // We store batsman strings on batting team; bowler string on bowling team separately in apply.
        $team['batsman1'] = $striker ? $this->formatBatsman($striker, true) : '';
        $team['batsman2'] = $nonStriker ? $this->formatBatsman($nonStriker, false) : '';

        if ($bowler) {
            $team['bowler'] = $this->formatBowler($bowler);
        }

        return $team;
    }

    public function syncBowlerToBowlingTeam(array $state): array
    {
        $battingKey = ($state['battingTeam'] ?? 'A') === 'A' ? 'teamA' : 'teamB';
        $bowlingKey = $battingKey === 'teamA' ? 'teamB' : 'teamA';

        $batting = $state[$battingKey];
        $bowling = $state[$bowlingKey];

        // Current bowler lives on bowling team
        $bowler = $this->findPlayer($bowling['bowlers'] ?? [], $bowling['currentBowlerId'] ?? null);
        $bowling['bowler'] = $bowler ? $this->formatBowler($bowler) : '';

        // Clear batting team's bowler display (not used)
        $batting['bowler'] = '';

        $state[$battingKey] = $this->deriveTeamStrings($batting);
        $state[$bowlingKey] = $bowling;

        return $state;
    }

    /* ── Setup ── */

    public function updateMeta(array $state, array $data): array
    {
        foreach ([
            'matchTitle', 'matchNo', 'totalOvers', 'visible', 'powerplay', 'displayMode',
            'playerOfMatch', 'organizerLogo', 'streamerLogo', 'playerAvatar', 'commentator',
            'autoGraphics', 'showLogo', 'showBoundariesCounter', 'boundariesScope', 'autoLoop', 'customMessage',
            'fieldZone',
        ] as $key) {
            if (array_key_exists($key, $data)) {
                $state[$key] = $data[$key];
            }
        }

        if (array_key_exists('knockout', $data) && is_array($data['knockout'])) {
            $state['knockout'] = $this->sanitizeKnockout($data['knockout']);
        }

        // Operator dismissed "end match?" after target — or cleared pending confirm.
        if (array_key_exists('pendingMatchEnd', $data)) {
            $pending = $data['pendingMatchEnd'];
            if ($pending === null || $pending === false || $pending === '') {
                $state['pendingMatchEnd'] = null;
                $state['pendingMatchEndDismissed'] = true;
            } elseif (is_array($pending)) {
                $state['pendingMatchEnd'] = $pending;
            }
        }
        if (array_key_exists('pendingMatchEndDismissed', $data)) {
            $state['pendingMatchEndDismissed'] = (bool) $data['pendingMatchEndDismissed'];
        }

        // Result / matchStatus: engine owns completion. Arbitrary result text cannot finish a live match.
        $forceComplete = ! empty($data['forceComplete']) || ! empty($data['adminOverride']);
        if (array_key_exists('result', $data)) {
            $result = trim((string) ($data['result'] ?? ''));
            if ($result === '') {
                $state['result'] = '';
                if (($state['matchStatus'] ?? '') === 'completed') {
                    $state['matchStatus'] = ((int) ($state['innings'] ?? 1) >= 2) ? 'live' : 'live';
                }
            } elseif ($forceComplete) {
                $state['result'] = $data['result'];
                $state['matchStatus'] = 'completed';
            }
            // else: ignore non-empty result patch during live / break (lifecycle protection)
        }

        // Clients may not force completed unless admin override.
        if (array_key_exists('matchStatus', $data) && ! $forceComplete) {
            $requested = (string) $data['matchStatus'];
            if ($requested === 'completed' && ($state['matchStatus'] ?? '') !== 'completed') {
                // ignore
            } elseif (in_array($requested, ['live', 'innings_break'], true)) {
                // Allow resume from completed only when clearing via result="" path above,
                // or explicit live after admin cleared result.
                if (($state['matchStatus'] ?? '') === 'completed' && $requested === 'live' && trim((string) ($state['result'] ?? '')) === '') {
                    $state['matchStatus'] = 'live';
                } elseif (($state['matchStatus'] ?? '') !== 'completed') {
                    // do not let clients invent innings_break
                }
            }
        } elseif (array_key_exists('matchStatus', $data) && $forceComplete) {
            $requested = (string) $data['matchStatus'];
            if (in_array($requested, ['live', 'innings_break', 'completed'], true)) {
                $state['matchStatus'] = $requested;
            }
        }

        if (isset($data['fieldPositions']) && is_array($data['fieldPositions'])) {
            $state['fieldPositions'] = $this->sanitizeFieldPositions($data['fieldPositions']);
        }

        if (isset($data['toss']) && is_array($data['toss'])) {
            $state['toss'] = array_merge($state['toss'] ?? [], [
                'winner' => $data['toss']['winner'] ?? ($state['toss']['winner'] ?? ''),
                'decision' => $data['toss']['decision'] ?? ($state['toss']['decision'] ?? ''),
                'text' => $data['toss']['text'] ?? ($state['toss']['text'] ?? ''),
            ]);
            if (empty($state['toss']['text']) && ! empty($state['toss']['winner'])) {
                $dec = $state['toss']['decision'] ?: 'bat';
                $state['toss']['text'] = sprintf("'%s' won the toss and elected to %s", $state['toss']['winner'], $dec);
            }
            // Toss decision is authoritative for first innings batting side (before play / during setup).
            $state = $this->applyTossToBattingTeam($state);
        }

        if (isset($data['teamA']) && is_array($data['teamA'])) {
            $state['teamA'] = array_merge($state['teamA'], $this->sanitizeTeamMeta($data['teamA']));
            if (isset($data['teamA']['squad']) && is_array($data['teamA']['squad'])) {
                $state['teamA']['squad'] = $this->sanitizeSquad($data['teamA']['squad']);
            }
        }
        if (isset($data['teamB']) && is_array($data['teamB'])) {
            $state['teamB'] = array_merge($state['teamB'], $this->sanitizeTeamMeta($data['teamB']));
            if (isset($data['teamB']['squad']) && is_array($data['teamB']['squad'])) {
                $state['teamB']['squad'] = $this->sanitizeSquad($data['teamB']['squad']);
            }
        }

        if (array_key_exists('battingTeam', $data)) {
            $status = $state['matchStatus'] ?? 'live';
            $innings = (int) ($state['innings'] ?? 1);
            if ($status === 'live' && $innings <= 1) {
                $state['battingTeam'] = $data['battingTeam'] === 'B' ? 'B' : 'A';
            }
        }

        return $this->finalize($state, false);
    }

    /**
     * Map toss winner + bat/bowl decision onto battingTeam for first innings.
     */
    public function applyTossToBattingTeam(array $state): array
    {
        // Only bind toss before the chase / break / completion.
        if (($state['matchStatus'] ?? 'live') !== 'live') {
            return $state;
        }
        if ((int) ($state['innings'] ?? 1) >= 2) {
            return $state;
        }

        $winner = trim((string) ($state['toss']['winner'] ?? ''));
        $decision = strtolower(trim((string) ($state['toss']['decision'] ?? 'bat')));
        if ($winner === '') {
            return $state;
        }

        $winnerKey = $this->resolveTeamKeyByName($state, $winner);
        if ($winnerKey === null) {
            return $state;
        }

        if ($decision === 'bowl' || $decision === 'bowling' || $decision === 'field') {
            $state['battingTeam'] = $winnerKey === 'A' ? 'B' : 'A';
        } else {
            $state['battingTeam'] = $winnerKey;
        }

        return $state;
    }

    protected function resolveTeamKeyByName(array $state, string $name): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        if (strcasecmp($name, (string) ($state['teamA']['name'] ?? '')) === 0) {
            return 'A';
        }
        if (strcasecmp($name, (string) ($state['teamB']['name'] ?? '')) === 0) {
            return 'B';
        }
        // Allow A/B or short names
        if (strcasecmp($name, 'A') === 0 || strcasecmp($name, (string) ($state['teamA']['shortName'] ?? '')) === 0) {
            return 'A';
        }
        if (strcasecmp($name, 'B') === 0 || strcasecmp($name, (string) ($state['teamB']['shortName'] ?? '')) === 0) {
            return 'B';
        }

        return null;
    }

    /** Push a deep snapshot for Undo (keeps last 40). */
    public function pushHistory(array $state): array
    {
        $copy = $state;
        unset($copy['history']);
        $history = $state['history'] ?? [];
        $history[] = $copy;
        if (count($history) > 40) {
            $history = array_slice($history, -40);
        }
        $state['history'] = $history;

        return $state;
    }

    public function undo(array $state): array
    {
        $history = $state['history'] ?? [];
        if (! count($history)) {
            return $this->finalize($state);
        }
        $prev = array_pop($history);
        $prev['history'] = $history;

        return $this->finalize($prev);
    }

    public function scorePenalty(array $state, int $runs = 5): array
    {
        if (! $this->canScore($state)) {
            return $this->finalize($state, false);
        }

        $runs = max(1, min(10, $runs));
        $battingKey = $this->battingKey($state);
        $batting = $state[$battingKey];
        $batting['score'] += $runs;
        $batting['extras']['pen'] = ($batting['extras']['pen'] ?? 0) + $runs;
        $state[$battingKey] = $batting;
        $state['thisOver'] = $this->pushThisOver($state['thisOver'] ?? [], 'P'.$runs, false);
        $state['lastEvent'] = ['type' => 'penalty', 'runs' => $runs];
        $state['ballLog'] = $this->pushBallLog($state, 'penalty', $runs, 'P'.$runs);

        return $this->finalize($state);
    }

    public function walkover(array $state, string $winnerTeam = 'A', string $reason = 'Walkover'): array
    {
        $winner = $winnerTeam === 'B' ? ($state['teamB']['name'] ?? 'Team B') : ($state['teamA']['name'] ?? 'Team A');
        $state['result'] = trim($winner.' won — '.$reason);
        $state['matchStatus'] = 'completed';
        $state['lastEvent'] = ['type' => 'walkover', 'winner' => $winnerTeam, 'reason' => $reason];

        return $this->finalize($state, false);
    }

    public function addSquadPlayer(array $state, string $team, string $name, string $avatar = ''): array
    {
        $key = $team === 'B' ? 'teamB' : 'teamA';
        $name = trim($name);
        if ($name === '') {
            return $this->finalize($state, false);
        }
        $squad = $state[$key]['squad'] ?? [];
        foreach ($squad as $p) {
            if (strcasecmp($p['name'] ?? '', $name) === 0) {
                return $this->finalize($state, false);
            }
        }
        $squad[] = $this->normalizeSquadPlayer([
            'id' => 'sq_'.substr(md5($name.microtime(true)), 0, 8),
            'name' => $name,
            'avatar' => $avatar,
        ]);
        $state[$key]['squad'] = $squad;

        return $this->finalize($state, false);
    }

    public function removeSquadPlayer(array $state, string $team, string $playerId): array
    {
        $key = $team === 'B' ? 'teamB' : 'teamA';
        $state[$key]['squad'] = array_values(array_filter(
            $state[$key]['squad'] ?? [],
            fn ($p) => ($p['id'] ?? '') !== $playerId
        ));

        return $this->finalize($state, false);
    }

    /**
     * Update one squad player's profile (avatar, role, styles, career history).
     */
    public function updateSquadPlayer(array $state, string $team, string $playerId, array $data): array
    {
        $key = $team === 'B' ? 'teamB' : 'teamA';
        $squad = $state[$key]['squad'] ?? [];
        $found = false;
        $updatedName = null;
        $updatedAvatar = null;

        foreach ($squad as $i => $p) {
            if (($p['id'] ?? '') !== $playerId) {
                continue;
            }
            $merged = array_merge($p, $data);
            $merged['id'] = $p['id'];
            if (! empty($data['name'])) {
                $merged['name'] = trim((string) $data['name']);
            } else {
                $merged['name'] = $p['name'] ?? '';
            }
            $squad[$i] = $this->normalizeSquadPlayer($merged);
            $updatedName = $squad[$i]['name'];
            $updatedAvatar = $squad[$i]['avatar'];
            $found = true;
            break;
        }

        if (! $found) {
            return $this->finalize($state, false);
        }

        $state[$key]['squad'] = $squad;

        // Keep live batsmen / bowlers avatar in sync for this player.
        if ($updatedName !== null) {
            foreach (['batsmen', 'bowlers'] as $listKey) {
                foreach ($state[$key][$listKey] ?? [] as $i => $row) {
                    if (strcasecmp($row['name'] ?? '', $updatedName) !== 0) {
                        continue;
                    }
                    $state[$key][$listKey][$i]['avatar'] = $updatedAvatar;
                }
            }
        }

        return $this->finalize($state, false);
    }

    public function addCommentary(array $state, string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return $this->finalize($state, false);
        }
        $log = $state['commentary'] ?? [];
        array_unshift($log, [
            'id' => 'c_'.substr(md5($text.microtime(true)), 0, 8),
            'text' => $text,
            'at' => now()->toIso8601String(),
            'over' => $state[$this->battingKey($state)]['overs'] ?? '0.0',
        ]);
        $state['commentary'] = array_slice($log, 0, 200);

        return $this->finalize($state, false);
    }

    public function sanitizeSquad(array $squad): array
    {
        $out = [];
        foreach ($squad as $p) {
            if (! is_array($p) || empty($p['name'])) {
                continue;
            }
            $out[] = $this->normalizeSquadPlayer($p, count($out));
        }

        return $out;
    }

    public function normalizeSquadPlayer(array $p, int $index = 0): array
    {
        $role = strtolower(trim((string) ($p['role'] ?? 'batsman')));
        if (! in_array($role, ['batsman', 'bowler', 'allrounder', 'wk'], true)) {
            $role = 'batsman';
        }
        $history = is_array($p['history'] ?? null) ? $p['history'] : [];

        return [
            'id' => $p['id'] ?? ('sq_'.substr(md5(($p['name'] ?? '').$index), 0, 8)),
            'name' => trim((string) ($p['name'] ?? '')),
            'avatar' => (string) ($p['avatar'] ?? ''),
            'role' => $role,
            'battingStyle' => trim((string) ($p['battingStyle'] ?? '')),
            'bowlingStyle' => trim((string) ($p['bowlingStyle'] ?? '')),
            'history' => [
                'matches' => (int) ($history['matches'] ?? $p['matches'] ?? 0),
                'runs' => (int) ($history['runs'] ?? $p['careerRuns'] ?? 0),
                'wickets' => (int) ($history['wickets'] ?? $p['careerWickets'] ?? 0),
                'average' => trim((string) ($history['average'] ?? $p['average'] ?? '')),
                'best' => trim((string) ($history['best'] ?? $p['best'] ?? '')),
                'strikeRate' => trim((string) ($history['strikeRate'] ?? $p['careerSr'] ?? '')),
            ],
        ];
    }

    /** Find squad profile by id or name across both teams. */
    public function findSquadPlayer(array $state, ?string $playerId = null, ?string $name = null, ?string $team = null): ?array
    {
        $keys = [];
        if ($team === 'A' || $team === 'B') {
            $keys[] = $team === 'B' ? 'teamB' : 'teamA';
        } else {
            $keys = ['teamA', 'teamB'];
        }

        foreach ($keys as $key) {
            $teamMeta = $state[$key] ?? [];
            foreach ($teamMeta['squad'] ?? [] as $p) {
                if ($playerId && ($p['id'] ?? '') === $playerId) {
                    return array_merge($this->normalizeSquadPlayer($p), [
                        'teamKey' => $key === 'teamB' ? 'B' : 'A',
                        'teamName' => $teamMeta['name'] ?? ($key === 'teamB' ? 'Team B' : 'Team A'),
                        'teamLogo' => $teamMeta['logo'] ?? '',
                    ]);
                }
                if ($name && strcasecmp($p['name'] ?? '', $name) === 0) {
                    return array_merge($this->normalizeSquadPlayer($p), [
                        'teamKey' => $key === 'teamB' ? 'B' : 'A',
                        'teamName' => $teamMeta['name'] ?? ($key === 'teamB' ? 'Team B' : 'Team A'),
                        'teamLogo' => $teamMeta['logo'] ?? '',
                    ]);
                }
            }
        }

        return null;
    }

    protected function sanitizeFieldPositions(array $positions): array
    {
        $out = [];
        foreach ($positions as $p) {
            if (! is_array($p)) {
                continue;
            }
            $out[] = [
                'id' => (string) ($p['id'] ?? ('fp_'.count($out))),
                'label' => trim((string) ($p['label'] ?? $p['id'] ?? 'Fielder')),
                'x' => max(0, min(100, (float) ($p['x'] ?? 50))),
                'y' => max(0, min(100, (float) ($p['y'] ?? 50))),
                'player' => trim((string) ($p['player'] ?? '')),
            ];
        }

        return count($out) ? $out : self::defaultFieldPositions();
    }

    protected function pushBallLog(array $state, string $type, int $runs, string $symbol): array
    {
        $log = $state['ballLog'] ?? [];
        $batting = $state[$this->battingKey($state)];
        $log[] = [
            'type' => $type,
            'runs' => $runs,
            'symbol' => $symbol,
            'innings' => $state['innings'] ?? 1,
            'team' => $state['battingTeam'] ?? 'A',
            'score' => $batting['score'] ?? 0,
            'wickets' => $batting['wickets'] ?? 0,
            'balls' => $batting['balls'] ?? 0,
            'over' => $batting['overs'] ?? '0.0',
        ];

        return array_slice($log, -600);
    }

    public function setBattingTeam(array $state, string $team): array
    {
        $state = $this->normalizeMatchStatus($state);
        if (! $this->canScore($state) && ($state['matchStatus'] ?? '') !== 'innings_break') {
            return $this->finalize($state, false);
        }
        // During break, batting side is locked until startSecondInnings.
        if (($state['matchStatus'] ?? '') === 'innings_break') {
            return $this->finalize($state, false);
        }

        $state['battingTeam'] = $team === 'B' ? 'B' : 'A';
        $state['thisOver'] = [];

        return $this->finalize($state, false);
    }

    public function setPlayers(array $state, array $data): array
    {
        if (! $this->canScore($state) && ($state['matchStatus'] ?? '') === 'completed') {
            return $this->finalize($state, false);
        }
        // Allow setting players during live; block during break/completed for bowling changes.
        if (($state['matchStatus'] ?? '') === 'innings_break' || ($state['matchStatus'] ?? '') === 'completed') {
            return $this->finalize($state, false);
        }

        $battingKey = $this->battingKey($state);
        $bowlingKey = $this->bowlingKey($state);
        $batting = $state[$battingKey];
        $bowling = $state[$bowlingKey];

        if (! empty($data['strikerName'])) {
            $batting = $this->ensureBatsman($batting, $data['strikerName'], true);
        }
        if (! empty($data['nonStrikerName'])) {
            $batting = $this->ensureBatsman($batting, $data['nonStrikerName'], false);
        }
        if (! empty($data['bowlerName'])) {
            $err = $this->validateBowlerSelection($state, $bowling, trim((string) $data['bowlerName']));
            if ($err !== null) {
                $state['lastEvent'] = ['type' => 'error', 'message' => $err];
                $state[$battingKey] = $batting;
                $state[$bowlingKey] = $bowling;

                return $this->finalize($state, false);
            }
            $bowling = $this->ensureBowler($bowling, $data['bowlerName']);
        }

        // Direct ID assignment
        if (array_key_exists('strikerId', $data)) {
            $batting['strikerId'] = $data['strikerId'];
        }
        if (array_key_exists('nonStrikerId', $data)) {
            $batting['nonStrikerId'] = $data['nonStrikerId'];
        }
        if (array_key_exists('currentBowlerId', $data) && $data['currentBowlerId']) {
            $bowler = $this->findPlayer($bowling['bowlers'] ?? [], $data['currentBowlerId']);
            $name = $bowler['name'] ?? '';
            if ($name !== '') {
                $err = $this->validateBowlerSelection($state, $bowling, $name);
                if ($err !== null) {
                    $state['lastEvent'] = ['type' => 'error', 'message' => $err];
                    $state[$battingKey] = $batting;
                    $state[$bowlingKey] = $bowling;

                    return $this->finalize($state, false);
                }
            }
            $bowling['currentBowlerId'] = $data['currentBowlerId'];
        }

        $state[$battingKey] = $batting;
        $state[$bowlingKey] = $bowling;

        return $this->finalize($state, false);
    }

    /**
     * @return string|null Error message, or null if OK.
     */
    public function validateBowlerSelection(array $state, array $bowlingTeam, string $bowlerName): ?string
    {
        $bowlerName = trim($bowlerName);
        if ($bowlerName === '') {
            return 'Select a bowler';
        }

        $maxOvers = $this->maxBowlerOvers($state);
        $maxBalls = $maxOvers * 6;
        $existing = null;
        foreach ($bowlingTeam['bowlers'] ?? [] as $b) {
            if (strcasecmp($b['name'] ?? '', $bowlerName) === 0) {
                $existing = $b;
                break;
            }
        }
        if ($existing && (int) ($existing['balls'] ?? 0) >= $maxBalls) {
            return $bowlerName.' has already bowled the maximum '.$maxOvers.' overs';
        }

        $lastId = $state['lastBowlerId'] ?? null;
        if ($lastId && $existing && ($existing['id'] ?? null) === $lastId) {
            return $bowlerName.' cannot bowl consecutive overs';
        }
        // New bowler matching last bowler by name (first selection after over)
        if ($lastId) {
            $last = $this->findPlayer($bowlingTeam['bowlers'] ?? [], $lastId);
            if ($last && strcasecmp($last['name'] ?? '', $bowlerName) === 0) {
                return $bowlerName.' cannot bowl consecutive overs';
            }
        }

        return null;
    }

    /** Whether this dismissal credits a wicket to the bowler. */
    public function dismissalCreditsBowler(string $dismissal): bool
    {
        $d = strtolower(trim($dismissal));
        if ($d === '') {
            return true;
        }
        // Non-bowler wickets
        if (preg_match('/run\s*out|retired|timed\s*out|obstruct|handled\s*the\s*ball|hit\s*the\s*ball\s*twice/', $d)) {
            return false;
        }

        return true;
    }

    /* ── Ball actions ── */

    public function scoreRuns(array $state, int $runs, bool $isBoundary = false): array
    {
        if (! $this->canScore($state)) {
            return $this->finalize($state, false);
        }

        $runs = max(0, min(7, $runs));
        $battingKey = $this->battingKey($state);
        $bowlingKey = $this->bowlingKey($state);
        $batting = $state[$battingKey];
        $bowling = $state[$bowlingKey];

        if (! $batting['strikerId'] || ! $bowling['currentBowlerId']) {
            return $this->finalize($state, false);
        }

        $strikerIdx = $this->playerIndex($batting['batsmen'], $batting['strikerId']);
        $bowlerIdx = $this->playerIndex($bowling['bowlers'], $bowling['currentBowlerId']);

        if ($strikerIdx === null || $bowlerIdx === null) {
            return $this->finalize($state);
        }

        $batting['batsmen'][$strikerIdx]['runs'] += $runs;
        $batting['batsmen'][$strikerIdx]['balls'] += 1;
        if ($runs === 4) {
            $batting['batsmen'][$strikerIdx]['fours'] = ($batting['batsmen'][$strikerIdx]['fours'] ?? 0) + 1;
        }
        if ($runs === 6) {
            $batting['batsmen'][$strikerIdx]['sixes'] = ($batting['batsmen'][$strikerIdx]['sixes'] ?? 0) + 1;
        }

        $bowling['bowlers'][$bowlerIdx]['runs'] += $runs;
        $bowling['bowlers'][$bowlerIdx]['balls'] += 1;
        if ($runs === 0) {
            $bowling['bowlers'][$bowlerIdx]['dots'] = (int) ($bowling['bowlers'][$bowlerIdx]['dots'] ?? 0) + 1;
        }

        $batting['score'] += $runs;
        $batting['balls'] += 1;

        $symbol = match ($runs) {
            0 => '•',
            4 => '4',
            6 => '6',
            default => (string) $runs,
        };
        $state['thisOver'] = $this->pushThisOver($state['thisOver'] ?? [], $symbol);
        $state['thisOverBowlerRuns'] = (int) ($state['thisOverBowlerRuns'] ?? 0) + $runs;

        // Odd runs rotate strike
        if ($runs % 2 === 1) {
            $batting = $this->rotateStrike($batting);
        }

        $state[$battingKey] = $batting;
        $state[$bowlingKey] = $bowling;
        $state['lastEvent'] = ['type' => 'runs', 'runs' => $runs];
        $state['ballLog'] = $this->pushBallLog($state, 'run', $runs, $symbol);

        $state = $this->checkOverComplete($state);

        return $this->finalize($state);
    }

    public function scoreWicket(array $state, string $dismissal = 'OUT', ?string $outgoingName = null): array
    {
        if (! $this->canScore($state)) {
            return $this->finalize($state, false);
        }

        $battingKey = $this->battingKey($state);
        $bowlingKey = $this->bowlingKey($state);
        $batting = $state[$battingKey];
        $bowling = $state[$bowlingKey];

        if (! $batting['strikerId'] || ! $bowling['currentBowlerId']) {
            return $this->finalize($state, false);
        }

        $strikerIdx = $this->playerIndex($batting['batsmen'], $batting['strikerId']);
        $bowlerIdx = $this->playerIndex($bowling['bowlers'], $bowling['currentBowlerId']);

        if ($strikerIdx === null || $bowlerIdx === null) {
            return $this->finalize($state);
        }

        $playerName = $batting['batsmen'][$strikerIdx]['name'];
        $batting['batsmen'][$strikerIdx]['out'] = true;
        $batting['batsmen'][$strikerIdx]['dismissal'] = $dismissal;
        $batting['batsmen'][$strikerIdx]['balls'] += 1;
        $batting['batsmen'][$strikerIdx]['onStrike'] = false;

        // Only credit the bowler for dismissals that belong to the bowler under the Laws.
        if ($this->dismissalCreditsBowler($dismissal) && $bowling['currentBowlerId']) {
            $bowling['bowlers'][$bowlerIdx]['wickets'] += 1;
        }
        $bowling['bowlers'][$bowlerIdx]['balls'] += 1;

        $batting['wickets'] = min(10, ($batting['wickets'] ?? 0) + 1);
        $batting['balls'] += 1;

        $state['thisOver'] = $this->pushThisOver($state['thisOver'] ?? [], 'W');
        $state['lastEvent'] = [
            'type' => 'wicket',
            'player' => $outgoingName ?: $playerName,
            'dismissal' => $dismissal,
        ];
        $state['lastOut'] = [
            'player' => $outgoingName ?: $playerName,
            'dismissal' => $dismissal,
            'runs' => $batting['batsmen'][$strikerIdx]['runs'] ?? 0,
            'balls' => $batting['batsmen'][$strikerIdx]['balls'] ?? 0,
            'fours' => $batting['batsmen'][$strikerIdx]['fours'] ?? 0,
            'sixes' => $batting['batsmen'][$strikerIdx]['sixes'] ?? 0,
        ];
        $state['ballLog'] = $this->pushBallLog($state, 'wicket', 0, 'W');

        // Clear striker — operator must set new batsman
        $batting['strikerId'] = null;

        $state[$battingKey] = $batting;
        $state[$bowlingKey] = $bowling;
        $state = $this->checkOverComplete($state);

        return $this->finalize($state);
    }

    public function scoreWide(array $state, int $extraRuns = 0): array
    {
        if (! $this->canScore($state)) {
            return $this->finalize($state, false);
        }

        $battingKey = $this->battingKey($state);
        $bowlingKey = $this->bowlingKey($state);
        $batting = $state[$battingKey];
        $bowling = $state[$bowlingKey];

        $total = 1 + max(0, $extraRuns);
        $batting['score'] += $total;
        $batting['extras']['wd'] = ($batting['extras']['wd'] ?? 0) + $total;

        if ($bowling['currentBowlerId']) {
            $idx = $this->playerIndex($bowling['bowlers'], $bowling['currentBowlerId']);
            if ($idx !== null) {
                $bowling['bowlers'][$idx]['runs'] += $total;
            }
        }

        $state['thisOver'] = $this->pushThisOver($state['thisOver'] ?? [], 'WD', false);
        $state['thisOverBowlerRuns'] = (int) ($state['thisOverBowlerRuns'] ?? 0) + $total;
        $state['lastEvent'] = ['type' => 'wide', 'runs' => $total];
        $state['ballLog'] = $this->pushBallLog($state, 'wide', $total, 'WD');
        $state[$battingKey] = $batting;
        $state[$bowlingKey] = $bowling;

        return $this->finalize($state);
    }

    public function scoreNoBall(array $state, int $batRuns = 0): array
    {
        if (! $this->canScore($state)) {
            return $this->finalize($state, false);
        }

        $battingKey = $this->battingKey($state);
        $bowlingKey = $this->bowlingKey($state);
        $batting = $state[$battingKey];
        $bowling = $state[$bowlingKey];
        $batRuns = max(0, min(6, $batRuns));
        $total = 1 + $batRuns;

        $batting['score'] += $total;
        $batting['extras']['nb'] = ($batting['extras']['nb'] ?? 0) + 1;

        if ($batting['strikerId'] && $batRuns > 0) {
            $idx = $this->playerIndex($batting['batsmen'], $batting['strikerId']);
            if ($idx !== null) {
                $batting['batsmen'][$idx]['runs'] += $batRuns;
                if ($batRuns === 4) {
                    $batting['batsmen'][$idx]['fours'] = ($batting['batsmen'][$idx]['fours'] ?? 0) + 1;
                }
                if ($batRuns === 6) {
                    $batting['batsmen'][$idx]['sixes'] = ($batting['batsmen'][$idx]['sixes'] ?? 0) + 1;
                }
            }
            if ($batRuns % 2 === 1) {
                $batting = $this->rotateStrike($batting);
            }
        }

        if ($bowling['currentBowlerId']) {
            $idx = $this->playerIndex($bowling['bowlers'], $bowling['currentBowlerId']);
            if ($idx !== null) {
                $bowling['bowlers'][$idx]['runs'] += $total;
            }
        }

        $state['thisOver'] = $this->pushThisOver($state['thisOver'] ?? [], 'NB', false);
        $state['thisOverBowlerRuns'] = (int) ($state['thisOverBowlerRuns'] ?? 0) + $total;
        $state['lastEvent'] = ['type' => 'noball', 'runs' => $total];
        $state['ballLog'] = $this->pushBallLog($state, 'noball', $total, 'NB');
        $state[$battingKey] = $batting;
        $state[$bowlingKey] = $bowling;

        return $this->finalize($state);
    }

    public function scoreBye(array $state, int $runs, bool $legBye = false): array
    {
        if (! $this->canScore($state)) {
            return $this->finalize($state, false);
        }

        $runs = max(1, min(4, $runs));
        $battingKey = $this->battingKey($state);
        $bowlingKey = $this->bowlingKey($state);
        $batting = $state[$battingKey];
        $bowling = $state[$bowlingKey];

        $batting['score'] += $runs;
        $key = $legBye ? 'lb' : 'b';
        $batting['extras'][$key] = ($batting['extras'][$key] ?? 0) + $runs;
        $batting['balls'] += 1;

        if ($batting['strikerId']) {
            $idx = $this->playerIndex($batting['batsmen'], $batting['strikerId']);
            if ($idx !== null) {
                $batting['batsmen'][$idx]['balls'] += 1;
            }
        }

        if ($bowling['currentBowlerId']) {
            $idx = $this->playerIndex($bowling['bowlers'], $bowling['currentBowlerId']);
            if ($idx !== null) {
                $bowling['bowlers'][$idx]['balls'] += 1;
            }
        }

        if ($runs % 2 === 1) {
            $batting = $this->rotateStrike($batting);
        }

        $state['thisOver'] = $this->pushThisOver($state['thisOver'] ?? [], $legBye ? 'LB' : 'B');
        $state['lastEvent'] = ['type' => $legBye ? 'legbye' : 'bye', 'runs' => $runs];
        $state['ballLog'] = $this->pushBallLog($state, $legBye ? 'legbye' : 'bye', $runs, $legBye ? 'LB' : 'B');
        $state[$battingKey] = $batting;
        $state[$bowlingKey] = $bowling;
        $state = $this->checkOverComplete($state);

        return $this->finalize($state);
    }

    public function rotateStrikeManual(array $state): array
    {
        if (! $this->canScore($state)) {
            return $this->finalize($state, false);
        }

        $key = $this->battingKey($state);
        $state[$key] = $this->rotateStrike($state[$key]);

        return $this->finalize($state, false);
    }

    /**
     * End the current innings.
     * 1st innings → innings_break (match stays active, target set).
     * 2nd innings → match completed with result.
     */
    public function endInnings(array $state): array
    {
        $state = $this->normalizeMatchStatus($state);
        $status = $state['matchStatus'] ?? 'live';

        // Already waiting to start chase — do not complete the match.
        if ($status === 'innings_break') {
            return $this->finalize($state, false);
        }
        if ($status === 'completed') {
            return $this->finalize($state, false);
        }

        $innings = (int) ($state['innings'] ?? 1);
        $battingKey = $this->battingKey($state);
        $finished = $state[$battingKey] ?? [];
        $finishedScore = (int) ($finished['score'] ?? 0);
        $finishedWkts = (int) ($finished['wickets'] ?? 0);
        $finishedOvers = $finished['overs'] ?? $this->ballsToOvers((int) ($finished['balls'] ?? 0));
        $finishedName = (string) ($finished['name'] ?? '');
        $finishedTeam = ($state['battingTeam'] ?? 'A') === 'B' ? 'B' : 'A';

        if ($innings <= 1) {
            // Close first innings only → innings break. Do NOT complete the match.
            $target = $finishedScore + 1;
            $state['target'] = $target;
            $state['matchStatus'] = 'innings_break';
            $state['innings'] = 1;
            $state['thisOver'] = [];
            $state['thisOverBowlerRuns'] = 0;
            $state['lastBowlerId'] = null;
            $state['result'] = '';
            $state['pendingMatchEnd'] = null;
            $state['pendingMatchEndDismissed'] = false;
            // Keep battingTeam as the side that just finished so scorecards stay correct.
            // startSecondInnings() switches sides.

            $state['lastEvent'] = [
                'type' => 'innings_break',
                'team' => $finishedTeam,
                'teamName' => $finishedName,
                'score' => $finishedScore,
                'wickets' => $finishedWkts,
                'overs' => $finishedOvers,
                'target' => $target,
                'matchComplete' => false,
            ];

            return $this->finalize($state, false);
        }

        // Second innings ended → decide result from chase
        $target = (int) ($state['target'] ?? ($finishedScore + 1));
        $chasingName = $finishedName !== '' ? $finishedName : 'Chasing side';
        $bowlingKey = $this->bowlingKey($state);
        $defendingName = (string) ($state[$bowlingKey]['name'] ?? 'Defending side');

        if ($finishedScore >= $target) {
            $margin = max(0, 10 - $finishedWkts);
            $state['result'] = $chasingName.' won by '.$margin.' wicket'.($margin === 1 ? '' : 's');
        } elseif ($finishedScore === ($target - 1)) {
            $state['result'] = 'Match tied';
        } else {
            $runsShort = max(0, $target - 1 - $finishedScore);
            $state['result'] = $defendingName.' won by '.$runsShort.' run'.($runsShort === 1 ? '' : 's');
        }

        $state['matchStatus'] = 'completed';
        $state['pendingMatchEnd'] = null;
        $state['pendingMatchEndDismissed'] = false;
        $state['thisOver'] = [];
        $state['thisOverBowlerRuns'] = 0;
        $state['lastBowlerId'] = null;
        $state['lastEvent'] = [
            'type' => 'innings_break',
            'team' => $finishedTeam,
            'teamName' => $finishedName,
            'score' => $finishedScore,
            'wickets' => $finishedWkts,
            'overs' => $finishedOvers,
            'target' => $target,
            'result' => $state['result'],
            'matchComplete' => true,
        ];

        return $this->finalize($state, false);
    }

    /**
     * Leave innings break and begin the chase. Preserves first-innings totals.
     */
    public function startSecondInnings(array $state): array
    {
        $state = $this->normalizeMatchStatus($state);
        if (($state['matchStatus'] ?? '') !== 'innings_break') {
            // Idempotent: already in chase
            if ((int) ($state['innings'] ?? 1) >= 2 && ($state['matchStatus'] ?? '') === 'live') {
                return $this->finalize($state, false);
            }

            return $this->finalize($state, false);
        }

        $finishedTeam = ($state['battingTeam'] ?? 'A') === 'B' ? 'B' : 'A';
        $nextTeam = $finishedTeam === 'A' ? 'B' : 'A';
        $nextKey = $nextTeam === 'A' ? 'teamA' : 'teamB';
        $bowlingKey = $finishedTeam === 'A' ? 'teamA' : 'teamB';
        // battingTeam is still the first-innings side during break
        $firstKey = $this->battingKey($state);
        $finishedScore = (int) ($state[$firstKey]['score'] ?? 0);
        $target = (int) ($state['target'] ?? ($finishedScore + 1));

        $state['target'] = $target;
        $state['battingTeam'] = $nextTeam;
        $state['innings'] = 2;
        $state['matchStatus'] = 'live';
        $state['thisOver'] = [];
        $state['thisOverBowlerRuns'] = 0;
        $state['lastBowlerId'] = null;
        $state['result'] = '';
        $state['pendingMatchEnd'] = null;
        $state['pendingMatchEndDismissed'] = false;

        // Fresh chase: clear strike / current bowler so operator sets XI for 2nd innings.
        // Do NOT reset first-innings team score/batsmen/bowlers.
        $state[$nextKey]['strikerId'] = null;
        $state[$nextKey]['nonStrikerId'] = null;
        $state[$bowlingKey]['currentBowlerId'] = null;

        $state['lastEvent'] = [
            'type' => 'start_innings',
            'innings' => 2,
            'battingTeam' => $nextTeam,
            'target' => $target,
        ];

        return $this->finalize($state, false);
    }

    public function reset(array $state): array
    {
        $fresh = self::defaultState();
        // Keep team names/colors if present
        $fresh['teamA']['name'] = $state['teamA']['name'] ?? 'Team A';
        $fresh['teamA']['shortName'] = $state['teamA']['shortName'] ?? 'TMA';
        $fresh['teamA']['primaryColor'] = $state['teamA']['primaryColor'] ?? '#e63946';
        $fresh['teamB']['name'] = $state['teamB']['name'] ?? 'Team B';
        $fresh['teamB']['shortName'] = $state['teamB']['shortName'] ?? 'TMB';
        $fresh['teamB']['primaryColor'] = $state['teamB']['primaryColor'] ?? '#1d8cf8';
        $fresh['teamA']['logo'] = $state['teamA']['logo'] ?? '';
        $fresh['teamB']['logo'] = $state['teamB']['logo'] ?? '';
        $fresh['teamA']['squad'] = $state['teamA']['squad'] ?? [];
        $fresh['teamB']['squad'] = $state['teamB']['squad'] ?? [];
        $fresh['organizerLogo'] = $state['organizerLogo'] ?? '';
        $fresh['streamerLogo'] = $state['streamerLogo'] ?? '';
        $fresh['themeId'] = $state['themeId'] ?? 'arena';
        $fresh['toss'] = $state['toss'] ?? $fresh['toss'];
        $fresh['knockout'] = isset($state['knockout']) && is_array($state['knockout'])
            ? $this->sanitizeKnockout($state['knockout'])
            : self::defaultKnockout(8);
        $fresh['matchNo'] = $state['matchNo'] ?? '1';
        $fresh['totalOvers'] = $state['totalOvers'] ?? 20;

        return $this->finalize($fresh, false);
    }

    /* ── Helpers ── */

    protected function finalize(array $state, bool $checkAutoEnd = true): array
    {
        $state = $this->ensureDerived($state);
        $state = $this->syncBowlerToBowlingTeam($state);
        if ($checkAutoEnd) {
            $state = $this->maybeAutoEndInnings($state);
        }

        return $state;
    }

    /**
     * Natural innings completion: overs done, all out.
     * Target reached → pending confirm (does NOT auto-complete the match).
     */
    protected function maybeAutoEndInnings(array $state): array
    {
        $state = $this->normalizeMatchStatus($state);
        if (($state['matchStatus'] ?? 'live') !== 'live') {
            return $state;
        }
        if (trim((string) ($state['result'] ?? '')) !== '') {
            return $state;
        }

        $battingKey = $this->battingKey($state);
        $batting = $state[$battingKey] ?? [];
        $innings = (int) ($state['innings'] ?? 1);
        $totalBalls = max(1, (int) ($state['totalOvers'] ?? 20)) * 6;
        $balls = (int) ($batting['balls'] ?? 0);
        $wickets = (int) ($batting['wickets'] ?? 0);
        $score = (int) ($batting['score'] ?? 0);

        $allOut = $wickets >= 10;
        $oversDone = $balls >= $totalBalls;
        $target = (int) ($state['target'] ?? 0);
        $targetReached = $innings >= 2 && $target > 0 && $score >= $target;

        // Below target again (e.g. after undo) → clear pending confirm state.
        if ($innings >= 2 && ! $targetReached) {
            $state['pendingMatchEnd'] = null;
            $state['pendingMatchEndDismissed'] = false;
        }

        // Target: ask operator before completing. Do not invent a winner yet.
        if ($targetReached) {
            if (! empty($state['pendingMatchEndDismissed'])) {
                return $state;
            }
            $state['pendingMatchEnd'] = [
                'reason' => 'target',
                'score' => $score,
                'target' => $target,
                'wickets' => $wickets,
                'teamName' => (string) ($batting['name'] ?? ''),
            ];
            $state['lastEvent'] = [
                'type' => 'target_reached',
                'score' => $score,
                'target' => $target,
                'wickets' => $wickets,
                'teamName' => (string) ($batting['name'] ?? ''),
                'matchComplete' => false,
            ];

            return $state;
        }

        if ($allOut || $oversDone) {
            return $this->endInnings($state);
        }

        return $state;
    }

    protected function battingKey(array $state): string
    {
        return ($state['battingTeam'] ?? 'A') === 'B' ? 'teamB' : 'teamA';
    }

    protected function bowlingKey(array $state): string
    {
        return $this->battingKey($state) === 'teamA' ? 'teamB' : 'teamA';
    }

    protected function sanitizeTeamMeta(array $data): array
    {
        $out = [];
        foreach (['name', 'shortName', 'primaryColor', 'secondaryColor', 'logo'] as $k) {
            if (array_key_exists($k, $data)) {
                $out[$k] = $data[$k];
            }
        }

        return $out;
    }

    protected function ensureBatsman(array $team, string $name, bool $asStriker): array
    {
        $name = trim($name);
        if ($name === '') {
            return $team;
        }

        $existing = null;
        foreach ($team['batsmen'] as $i => $b) {
            if (strcasecmp($b['name'] ?? '', $name) !== 0) {
                continue;
            }
            if (! empty($b['out'])) {
                $team['batsmen'][$i]['out'] = false;
                $team['batsmen'][$i]['dismissal'] = '';
            }
            $existing = $b['id'];
            break;
        }

        if (! $existing) {
            $id = 'bat_'.substr(md5($name.microtime(true)), 0, 8);
            $avatar = '';
            foreach ($team['squad'] ?? [] as $p) {
                if (strcasecmp($p['name'] ?? '', $name) === 0) {
                    $avatar = (string) ($p['avatar'] ?? '');
                    break;
                }
            }
            $team['batsmen'][] = [
                'id' => $id,
                'name' => $name,
                'runs' => 0,
                'balls' => 0,
                'fours' => 0,
                'sixes' => 0,
                'out' => false,
                'dismissal' => '',
                'onStrike' => $asStriker,
                'avatar' => $avatar,
            ];
            $existing = $id;
        }

        if ($asStriker) {
            $team['strikerId'] = $existing;
            foreach ($team['batsmen'] as $i => $b) {
                $team['batsmen'][$i]['onStrike'] = ($b['id'] === $existing);
            }
        } else {
            $team['nonStrikerId'] = $existing;
        }

        return $team;
    }

    protected function ensureBowler(array $team, string $name): array
    {
        $name = trim($name);
        $existing = null;
        foreach ($team['bowlers'] as $b) {
            if (strcasecmp($b['name'], $name) === 0) {
                $existing = $b['id'];
                break;
            }
        }

        if (! $existing) {
            $id = 'bowl_'.substr(md5($name.microtime(true)), 0, 8);
            $avatar = '';
            foreach ($team['squad'] ?? [] as $p) {
                if (strcasecmp($p['name'] ?? '', $name) === 0) {
                    $avatar = (string) ($p['avatar'] ?? '');
                    break;
                }
            }
            $team['bowlers'][] = [
                'id' => $id,
                'name' => $name,
                'balls' => 0,
                'runs' => 0,
                'wickets' => 0,
                'maidens' => 0,
                'dots' => 0,
                'avatar' => $avatar,
            ];
            $existing = $id;
        }

        $team['currentBowlerId'] = $existing;

        return $team;
    }

    protected function rotateStrike(array $team): array
    {
        $s = $team['strikerId'];
        $n = $team['nonStrikerId'];
        $team['strikerId'] = $n;
        $team['nonStrikerId'] = $s;
        foreach ($team['batsmen'] as $i => $b) {
            $team['batsmen'][$i]['onStrike'] = ($b['id'] === $team['strikerId']);
        }

        return $team;
    }

    protected function checkOverComplete(array $state): array
    {
        $battingKey = $this->battingKey($state);
        $bowlingKey = $this->bowlingKey($state);
        $batting = $state[$battingKey];
        $bowling = $state[$bowlingKey];

        // Count legal balls in this over from team balls mod 6
        if (($batting['balls'] % 6) === 0 && $batting['balls'] > 0) {
            $finishedBowlerId = $bowling['currentBowlerId'] ?? null;
            if ($finishedBowlerId) {
                $idx = $this->playerIndex($bowling['bowlers'], $finishedBowlerId);
                if ($idx !== null) {
                    // Maiden: zero runs conceded by the bowler in this over (byes/LB excluded).
                    $bowlerRuns = (int) ($state['thisOverBowlerRuns'] ?? 0);
                    if ($bowlerRuns === 0) {
                        $bowling['bowlers'][$idx]['maidens'] = (int) ($bowling['bowlers'][$idx]['maidens'] ?? 0) + 1;
                    }
                }
                $state['lastBowlerId'] = $finishedBowlerId;
            }

            $state['thisOver'] = [];
            $state['thisOverBowlerRuns'] = 0;
            // Rotate strike at end of over
            $batting = $this->rotateStrike($batting);
            // Clear current bowler so operator picks next (cannot be lastBowlerId)
            $bowling['currentBowlerId'] = null;
            $bowling['bowler'] = '';
            $state['lastEvent'] = array_merge($state['lastEvent'] ?? [], ['overComplete' => true]);
        }

        $state[$battingKey] = $batting;
        $state[$bowlingKey] = $bowling;

        return $state;
    }

    protected function pushThisOver(array $over, string $symbol, bool $legal = true): array
    {
        $over[] = $symbol;
        if ($legal && count(array_filter($over, fn ($b) => ! in_array($b, ['WD', 'NB'], true))) >= 6) {
            // Will be cleared by checkOverComplete
        }

        return $over;
    }

    protected function findPlayer(array $list, ?string $id): ?array
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

    protected function playerIndex(array $list, ?string $id): ?int
    {
        if (! $id) {
            return null;
        }
        foreach ($list as $i => $p) {
            if (($p['id'] ?? null) === $id) {
                return $i;
            }
        }

        return null;
    }

    protected function formatBatsman(array $p, bool $onStrike): string
    {
        $star = $onStrike ? '*' : '';

        return sprintf('%s %d%s (%d)', $p['name'], $p['runs'], $star, $p['balls']);
    }

    protected function formatBowler(array $p): string
    {
        $overs = $this->ballsToOvers($p['balls'] ?? 0);

        return sprintf('%s %d/%d (%s)', $p['name'], $p['wickets'] ?? 0, $p['runs'] ?? 0, $overs);
    }

    public function ballsToOvers(int $balls): string
    {
        $o = intdiv($balls, 6);
        $b = $balls % 6;

        return "{$o}.{$b}";
    }

    /** Default knockout bracket (4 / 8 / 16 team). Sides L|R|C for tree layout. */
    public static function defaultKnockout(int $format = 8): array
    {
        $format = match ((int) $format) {
            4 => 4,
            16 => 16,
            default => 8,
        };

        $matches = [];

        if ($format === 16) {
            $r16 = [
                ['r16_1', 'L', 'A', 'R16 · GROUP A1'],
                ['r16_2', 'L', 'A', 'R16 · GROUP A2'],
                ['r16_3', 'L', 'B', 'R16 · GROUP B1'],
                ['r16_4', 'L', 'B', 'R16 · GROUP B2'],
                ['r16_5', 'R', 'C', 'R16 · GROUP C1'],
                ['r16_6', 'R', 'C', 'R16 · GROUP C2'],
                ['r16_7', 'R', 'D', 'R16 · GROUP D1'],
                ['r16_8', 'R', 'D', 'R16 · GROUP D2'],
            ];
            foreach ($r16 as [$slot, $side, $group, $label]) {
                $matches[] = self::koMatch($slot, 'R16', $label, $side, $group);
            }
        }

        if ($format >= 8) {
            $qf = [
                ['qf1', 'L', 'A', 'QF 1'],
                ['qf2', 'L', 'B', 'QF 2'],
                ['qf3', 'R', 'C', 'QF 3'],
                ['qf4', 'R', 'D', 'QF 4'],
            ];
            foreach ($qf as [$slot, $side, $group, $label]) {
                $matches[] = self::koMatch($slot, 'QF', $label, $side, $group);
            }
        }

        $matches[] = self::koMatch('sf1', 'SF', 'SF 1', 'L', '');
        $matches[] = self::koMatch('sf2', 'SF', 'SF 2', 'R', '');
        $matches[] = self::koMatch('final', 'F', 'FINAL', 'C', '');

        return [
            'title' => 'KNOCKOUT ROUND',
            'format' => $format,
            'roundDates' => [
                'r16' => '',
                'qf' => '',
                'sf' => '',
                'final' => '',
            ],
            'matches' => $matches,
        ];
    }

    protected static function koMatch(string $slot, string $round, string $label, string $side, string $group): array
    {
        return [
            'slot' => $slot,
            'round' => $round,
            'label' => $label,
            'side' => $side,
            'group' => $group,
            'teamA' => '',
            'teamB' => '',
            'time' => '',
        ];
    }

    protected function sanitizeKnockout(array $data): array
    {
        $format = match ((int) ($data['format'] ?? 8)) {
            4 => 4,
            16 => 16,
            default => 8,
        };

        $allowedRounds = ['R64', 'R32', 'R16', 'QF', 'SF', 'F'];
        $roundOrder = array_flip($allowedRounds);

        $matches = [];
        $seen = [];
        foreach (($data['matches'] ?? []) as $idx => $m) {
            if (! is_array($m)) {
                continue;
            }
            $round = strtoupper(trim((string) ($m['round'] ?? '')));
            if (! in_array($round, $allowedRounds, true)) {
                continue;
            }
            $slot = strtolower(trim((string) ($m['slot'] ?? '')));
            if ($slot === '' || isset($seen[$slot])) {
                $n = 1;
                do {
                    $slot = strtolower($round).'_'.$n;
                    $n++;
                } while (isset($seen[$slot]));
            }
            $seen[$slot] = true;
            $label = trim((string) ($m['label'] ?? ''));
            if ($label === '') {
                $countInRound = 0;
                foreach ($matches as $existing) {
                    if ($existing['round'] === $round) {
                        $countInRound++;
                    }
                }
                $label = $round === 'F' ? 'FINAL' : ($round.' '.($countInRound + 1));
            }
            $matches[] = [
                'slot' => $slot,
                'round' => $round,
                'label' => $label,
                'side' => in_array(strtoupper((string) ($m['side'] ?? '')), ['L', 'R', 'C'], true)
                    ? strtoupper((string) $m['side'])
                    : ($round === 'F' ? 'C' : 'L'),
                'group' => strtoupper(trim((string) ($m['group'] ?? ''))),
                'teamA' => trim((string) ($m['teamA'] ?? '')),
                'teamB' => trim((string) ($m['teamB'] ?? '')),
                'time' => trim((string) ($m['time'] ?? '')),
            ];
        }

        $custom = (bool) ($data['custom'] ?? false);
        if ($matches === []) {
            $base = self::defaultKnockout($format);

            return array_merge($base, [
                'custom' => false,
                'title' => trim((string) ($data['title'] ?? '')) ?: $base['title'],
                'roundDates' => [
                    'r64' => trim((string) (is_array($data['roundDates'] ?? null) ? ($data['roundDates']['r64'] ?? '') : '')),
                    'r32' => trim((string) (is_array($data['roundDates'] ?? null) ? ($data['roundDates']['r32'] ?? '') : '')),
                    'r16' => trim((string) (is_array($data['roundDates'] ?? null) ? ($data['roundDates']['r16'] ?? '') : '')),
                    'qf' => trim((string) (is_array($data['roundDates'] ?? null) ? ($data['roundDates']['qf'] ?? '') : '')),
                    'sf' => trim((string) (is_array($data['roundDates'] ?? null) ? ($data['roundDates']['sf'] ?? '') : '')),
                    'final' => trim((string) (is_array($data['roundDates'] ?? null) ? ($data['roundDates']['final'] ?? '') : '')),
                ],
            ]);
        }

        // Stable sort: round order, then original index
        usort($matches, function ($a, $b) use ($roundOrder) {
            $ra = $roundOrder[$a['round']] ?? 99;
            $rb = $roundOrder[$b['round']] ?? 99;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return strcmp($a['slot'], $b['slot']);
        });

        // Ensure at least one Final
        $hasFinal = false;
        foreach ($matches as $m) {
            if ($m['round'] === 'F') {
                $hasFinal = true;
                break;
            }
        }
        if (! $hasFinal) {
            $matches[] = self::koMatch('final', 'F', 'FINAL', 'C', '');
        }

        $rdIn = is_array($data['roundDates'] ?? null) ? $data['roundDates'] : [];
        $title = trim((string) ($data['title'] ?? ''));

        // Detect custom vs preset by comparing slot sets
        $baseSlots = array_column(self::defaultKnockout($format)['matches'], 'slot');
        $haveSlots = array_column($matches, 'slot');
        sort($baseSlots);
        $sortedHave = $haveSlots;
        sort($sortedHave);
        if ($baseSlots !== $sortedHave) {
            $custom = true;
        }

        return [
            'title' => $title !== '' ? $title : 'KNOCKOUT ROUND',
            'format' => $format,
            'custom' => $custom,
            'roundDates' => [
                'r64' => trim((string) ($rdIn['r64'] ?? '')),
                'r32' => trim((string) ($rdIn['r32'] ?? '')),
                'r16' => trim((string) ($rdIn['r16'] ?? '')),
                'qf' => trim((string) ($rdIn['qf'] ?? '')),
                'sf' => trim((string) ($rdIn['sf'] ?? '')),
                'final' => trim((string) ($rdIn['final'] ?? '')),
            ],
            'matches' => $matches,
        ];
    }
}
