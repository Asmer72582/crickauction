<?php

namespace Tests\Unit;

use App\Services\CricketScoringService;
use PHPUnit\Framework\TestCase;

class MatchLifecycleTest extends TestCase
{
    protected CricketScoringService $scoring;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scoring = new CricketScoringService;
    }

    protected function baseState(array $overrides = []): array
    {
        $state = CricketScoringService::defaultState();
        $state['teamA']['name'] = 'Team A';
        $state['teamB']['name'] = 'Team B';
        $state['totalOvers'] = 20;
        $state['battingTeam'] = 'A';
        $state['innings'] = 1;
        $state['matchStatus'] = 'live';

        return array_replace_recursive($state, $overrides);
    }

    protected function withFirstInningsScore(array $state, int $runs, int $wickets = 6, int $balls = 120): array
    {
        $state['teamA']['score'] = $runs;
        $state['teamA']['wickets'] = $wickets;
        $state['teamA']['balls'] = $balls;
        $state['teamA']['batsmen'] = [
            ['id' => 'b1', 'name' => 'Batter 1', 'runs' => $runs, 'balls' => 40, 'out' => false, 'fours' => 0, 'sixes' => 0],
        ];
        $state['teamA']['strikerId'] = 'b1';
        $state['teamB']['bowlers'] = [
            ['id' => 'bw1', 'name' => 'Bowler 1', 'runs' => $runs, 'balls' => $balls, 'wickets' => $wickets, 'maidens' => 0, 'dots' => 0],
        ];
        $state['teamB']['currentBowlerId'] = 'bw1';

        return $this->scoring->ensureDerived($state);
    }

    public function test_toss_bat_sets_winner_as_batting_team(): void
    {
        $state = $this->baseState();
        $state = $this->scoring->updateMeta($state, [
            'toss' => ['winner' => 'Team A', 'decision' => 'bat'],
        ]);

        $this->assertSame('A', $state['battingTeam']);
        $this->assertStringContainsString('elected to bat', $state['toss']['text']);
    }

    public function test_toss_bowl_sets_other_team_batting(): void
    {
        $state = $this->baseState();
        $state = $this->scoring->updateMeta($state, [
            'toss' => ['winner' => 'Team A', 'decision' => 'bowl'],
        ]);

        $this->assertSame('B', $state['battingTeam']);
    }

    public function test_requires_toss_before_play(): void
    {
        $state = $this->baseState();
        $this->assertTrue($this->scoring->requiresToss($state));
        $this->assertFalse($this->scoring->isTossComplete($state));

        $state = $this->scoring->updateMeta($state, [
            'toss' => ['winner' => 'Team B', 'decision' => 'bowl'],
        ]);
        $this->assertTrue($this->scoring->isTossComplete($state));
        $this->assertFalse($this->scoring->requiresToss($state));
        $this->assertSame('A', $state['battingTeam']); // B chose bowl → A bats
    }

    public function test_end_first_innings_goes_to_break_not_completed(): void
    {
        $state = $this->withFirstInningsScore($this->baseState(), 163, 6, 120);
        $state = $this->scoring->endInnings($state);

        $this->assertSame('innings_break', $state['matchStatus']);
        $this->assertSame(1, (int) $state['innings']);
        $this->assertSame('', trim((string) $state['result']));
        $this->assertSame(164, (int) $state['target']);
        $this->assertSame('A', $state['battingTeam']);
        $this->assertFalse(! empty($state['lastEvent']['matchComplete']));
        $this->assertFalse($this->scoring->isMatchCompleted($state));
    }

    public function test_repeat_end_innings_during_break_is_idempotent(): void
    {
        $state = $this->withFirstInningsScore($this->baseState(), 163, 6, 120);
        $state = $this->scoring->endInnings($state);
        $again = $this->scoring->endInnings($state);

        $this->assertSame('innings_break', $again['matchStatus']);
        $this->assertSame(1, (int) $again['innings']);
        $this->assertSame('', trim((string) $again['result']));
        $this->assertSame(164, (int) $again['target']);
    }

    public function test_start_second_innings_switches_teams_and_preserves_first(): void
    {
        $state = $this->withFirstInningsScore($this->baseState(), 163, 6, 120);
        $state = $this->scoring->endInnings($state);
        $state = $this->scoring->startSecondInnings($state);

        $this->assertSame('live', $state['matchStatus']);
        $this->assertSame(2, (int) $state['innings']);
        $this->assertSame('B', $state['battingTeam']);
        $this->assertSame(164, (int) $state['target']);
        $this->assertSame(163, (int) $state['teamA']['score']);
        $this->assertSame(6, (int) $state['teamA']['wickets']);
        $this->assertSame(0, (int) $state['teamB']['score']);
        $this->assertSame(0, (int) $state['teamB']['wickets']);
        $this->assertSame(0, (int) $state['teamB']['balls']);
    }

    public function test_repeat_start_second_innings_is_idempotent(): void
    {
        $state = $this->withFirstInningsScore($this->baseState(), 163, 6, 120);
        $state = $this->scoring->endInnings($state);
        $state = $this->scoring->startSecondInnings($state);
        $scoreA = $state['teamA']['score'];
        $again = $this->scoring->startSecondInnings($state);

        $this->assertSame(2, (int) $again['innings']);
        $this->assertSame($scoreA, $again['teamA']['score']);
        $this->assertSame('B', $again['battingTeam']);
    }

    public function test_chase_target_completes_match_with_wicket_win(): void
    {
        $state = $this->withFirstInningsScore($this->baseState(), 163, 6, 120);
        $state = $this->scoring->endInnings($state);
        $state = $this->scoring->startSecondInnings($state);

        $state['teamB']['score'] = 164;
        $state['teamB']['wickets'] = 6;
        $state['teamB']['balls'] = 112;
        $state = $this->scoring->endInnings($state);

        $this->assertSame('completed', $state['matchStatus']);
        $this->assertStringContainsString('Team B won by 4 wicket', $state['result']);
        $this->assertSame(163, (int) $state['teamA']['score']);
    }

    public function test_all_out_defending_side_wins_by_runs(): void
    {
        $state = $this->withFirstInningsScore($this->baseState(), 163, 6, 120);
        $state = $this->scoring->endInnings($state);
        $state = $this->scoring->startSecondInnings($state);

        $state['teamB']['score'] = 150;
        $state['teamB']['wickets'] = 10;
        $state['teamB']['balls'] = 100;
        $state = $this->scoring->endInnings($state);

        $this->assertSame('completed', $state['matchStatus']);
        $this->assertStringContainsString('Team A won by 13 run', $state['result']);
    }

    public function test_overs_exhausted_defending_side_wins(): void
    {
        $state = $this->withFirstInningsScore($this->baseState(), 163, 6, 120);
        $state = $this->scoring->endInnings($state);
        $state = $this->scoring->startSecondInnings($state);

        $state['teamB']['score'] = 150;
        $state['teamB']['wickets'] = 7;
        $state['teamB']['balls'] = 120;
        $state = $this->scoring->endInnings($state);

        $this->assertSame('completed', $state['matchStatus']);
        $this->assertStringContainsString('Team A won by 13 run', $state['result']);
    }

    public function test_tie_completes_match(): void
    {
        $state = $this->withFirstInningsScore($this->baseState(), 163, 6, 120);
        $state = $this->scoring->endInnings($state);
        $state = $this->scoring->startSecondInnings($state);

        $state['teamB']['score'] = 163;
        $state['teamB']['wickets'] = 7;
        $state['teamB']['balls'] = 120;
        $state = $this->scoring->endInnings($state);

        $this->assertSame('completed', $state['matchStatus']);
        $this->assertSame('Match tied', $state['result']);
    }

    public function test_patch_result_during_innings_one_does_not_complete(): void
    {
        $state = $this->withFirstInningsScore($this->baseState(), 163, 6, 60);
        $state = $this->scoring->updateMeta($state, [
            'result' => 'Team A won by everything',
        ]);

        $this->assertSame('live', $state['matchStatus']);
        $this->assertSame('', trim((string) $state['result']));
        $this->assertFalse($this->scoring->isMatchCompleted($state));
    }

    public function test_admin_force_complete_can_set_result(): void
    {
        $state = $this->baseState();
        $state = $this->scoring->updateMeta($state, [
            'result' => 'Team A won — admin',
            'forceComplete' => true,
        ]);

        $this->assertSame('completed', $state['matchStatus']);
        $this->assertStringContainsString('admin', $state['result']);
    }

    public function test_run_out_does_not_credit_bowler_wicket(): void
    {
        $state = $this->baseState(['totalOvers' => 5]);
        $state = $this->scoring->setPlayers($state, [
            'strikerName' => 'Bat1',
            'nonStrikerName' => 'Bat2',
            'bowlerName' => 'Bowl1',
        ]);
        $before = 0;
        foreach ($state['teamB']['bowlers'] as $b) {
            if ($b['name'] === 'Bowl1') {
                $before = (int) ($b['wickets'] ?? 0);
            }
        }

        $state = $this->scoring->scoreWicket($state, 'run out');

        $after = $before;
        foreach ($state['teamB']['bowlers'] as $b) {
            if ($b['name'] === 'Bowl1') {
                $after = (int) ($b['wickets'] ?? 0);
            }
        }
        $this->assertSame($before, $after);
        $this->assertSame(1, (int) $state['teamA']['wickets']);
    }

    public function test_bowled_credits_bowler_wicket(): void
    {
        $state = $this->baseState(['totalOvers' => 5]);
        $state = $this->scoring->setPlayers($state, [
            'strikerName' => 'Bat1',
            'nonStrikerName' => 'Bat2',
            'bowlerName' => 'Bowl1',
        ]);
        $state = $this->scoring->scoreWicket($state, 'b');

        $wkts = 0;
        foreach ($state['teamB']['bowlers'] as $b) {
            if ($b['name'] === 'Bowl1') {
                $wkts = (int) ($b['wickets'] ?? 0);
            }
        }
        $this->assertSame(1, $wkts);
    }

    public function test_wide_does_not_increment_legal_balls(): void
    {
        $state = $this->baseState(['totalOvers' => 5]);
        $state = $this->scoring->setPlayers($state, [
            'strikerName' => 'Bat1',
            'nonStrikerName' => 'Bat2',
            'bowlerName' => 'Bowl1',
        ]);
        $before = (int) $state['teamA']['balls'];
        $state = $this->scoring->scoreWide($state, 0);
        $this->assertSame($before, (int) $state['teamA']['balls']);
        $this->assertSame(1, (int) $state['teamA']['score']);
    }

    public function test_noball_does_not_increment_legal_balls(): void
    {
        $state = $this->baseState(['totalOvers' => 5]);
        $state = $this->scoring->setPlayers($state, [
            'strikerName' => 'Bat1',
            'nonStrikerName' => 'Bat2',
            'bowlerName' => 'Bowl1',
        ]);
        $before = (int) $state['teamA']['balls'];
        $state = $this->scoring->scoreNoBall($state, 0);
        $this->assertSame($before, (int) $state['teamA']['balls']);
        $this->assertSame(1, (int) $state['teamA']['score']);
    }

    public function test_six_legal_deliveries_complete_over_and_clear_bowler(): void
    {
        $state = $this->baseState(['totalOvers' => 5]);
        $state = $this->scoring->setPlayers($state, [
            'strikerName' => 'Bat1',
            'nonStrikerName' => 'Bat2',
            'bowlerName' => 'Bowl1',
        ]);
        $bowlerId = $state['teamB']['currentBowlerId'];
        for ($i = 0; $i < 6; $i++) {
            $state = $this->scoring->scoreRuns($state, 0);
        }

        $this->assertSame(6, (int) $state['teamA']['balls']);
        $this->assertNull($state['teamB']['currentBowlerId']);
        $this->assertSame($bowlerId, $state['lastBowlerId']);
        $this->assertTrue(! empty($state['lastEvent']['overComplete']));
        $maidens = 0;
        foreach ($state['teamB']['bowlers'] as $b) {
            if (($b['id'] ?? '') === $bowlerId) {
                $maidens = (int) ($b['maidens'] ?? 0);
            }
        }
        $this->assertSame(1, $maidens);
    }

    public function test_consecutive_bowler_rejected(): void
    {
        $state = $this->baseState(['totalOvers' => 20]); // max 4 overs — one over is fine for quota
        $state = $this->scoring->setPlayers($state, [
            'strikerName' => 'Bat1',
            'nonStrikerName' => 'Bat2',
            'bowlerName' => 'Bowl1',
        ]);
        for ($i = 0; $i < 6; $i++) {
            $state = $this->scoring->scoreRuns($state, 0);
        }
        $state = $this->scoring->setPlayers($state, ['bowlerName' => 'Bowl1']);
        $this->assertSame('error', $state['lastEvent']['type'] ?? null);
        $this->assertStringContainsString('consecutive', $state['lastEvent']['message'] ?? '');
    }

    public function test_max_bowler_overs_enforced(): void
    {
        $state = $this->baseState(['totalOvers' => 20]); // max 4 overs = 24 balls
        $this->assertSame(4, $this->scoring->maxBowlerOvers($state));

        $state = $this->scoring->setPlayers($state, [
            'strikerName' => 'Bat1',
            'nonStrikerName' => 'Bat2',
            'bowlerName' => 'Bowl1',
        ]);
        $state['teamB']['bowlers'][0]['balls'] = 24;
        $state['teamB']['currentBowlerId'] = null;
        $state['lastBowlerId'] = 'someone_else';
        $state = $this->scoring->setPlayers($state, ['bowlerName' => 'Bowl1']);
        $this->assertSame('error', $state['lastEvent']['type'] ?? null);
        $this->assertStringContainsString('maximum', $state['lastEvent']['message'] ?? '');
    }

    public function test_scoring_blocked_during_innings_break(): void
    {
        $state = $this->withFirstInningsScore($this->baseState(), 100, 2, 60);
        $state = $this->scoring->endInnings($state);
        $before = $state['teamA']['score'];
        $state = $this->scoring->scoreRuns($state, 4);
        $this->assertSame('innings_break', $state['matchStatus']);
        $this->assertSame($before, (int) $state['teamA']['score']);
    }

    public function test_target_reached_sets_pending_confirm_not_auto_complete(): void
    {
        $state = $this->withFirstInningsScore($this->baseState(['totalOvers' => 5]), 50, 2, 30);
        $state = $this->scoring->endInnings($state);
        $state = $this->scoring->startSecondInnings($state);

        $state['teamB']['score'] = 50;
        $state['teamB']['wickets'] = 1;
        $state['teamB']['balls'] = 20;
        $state['teamB']['batsmen'] = [
            ['id' => 'c1', 'name' => 'C1', 'runs' => 50, 'balls' => 20, 'out' => false, 'fours' => 0, 'sixes' => 0],
        ];
        $state['teamB']['strikerId'] = 'c1';
        $state['teamA']['bowlers'] = [
            ['id' => 'aw1', 'name' => 'AW', 'runs' => 50, 'balls' => 20, 'wickets' => 1, 'maidens' => 0, 'dots' => 0],
        ];
        $state['teamA']['currentBowlerId'] = 'aw1';
        $state = $this->scoring->ensureDerived($state);
        $state = $this->scoring->scoreRuns($state, 1);

        $this->assertSame('live', $state['matchStatus']);
        $this->assertSame('target', $state['pendingMatchEnd']['reason'] ?? null);
        $this->assertSame('target_reached', $state['lastEvent']['type'] ?? null);

        $state = $this->scoring->endInnings($state);
        $this->assertSame('completed', $state['matchStatus']);
        $this->assertStringContainsString('Team B won by', $state['result']);
        $this->assertNull($state['pendingMatchEnd']);
    }

    public function test_dismiss_target_confirm_does_not_reprompt_until_below_target(): void
    {
        $state = $this->withFirstInningsScore($this->baseState(['totalOvers' => 5]), 50, 2, 30);
        $state = $this->scoring->endInnings($state);
        $state = $this->scoring->startSecondInnings($state);

        $state['teamB']['score'] = 50;
        $state['teamB']['wickets'] = 0;
        $state['teamB']['balls'] = 12;
        $state['teamB']['batsmen'] = [
            ['id' => 'c1', 'name' => 'C1', 'runs' => 50, 'balls' => 12, 'out' => false, 'fours' => 0, 'sixes' => 0],
        ];
        $state['teamB']['strikerId'] = 'c1';
        $state['teamA']['bowlers'] = [
            ['id' => 'aw1', 'name' => 'AW', 'runs' => 50, 'balls' => 12, 'wickets' => 0, 'maidens' => 0, 'dots' => 0],
        ];
        $state['teamA']['currentBowlerId'] = 'aw1';
        $state = $this->scoring->ensureDerived($state);
        $state = $this->scoring->scoreRuns($state, 1); // 51 >= target 51

        $this->assertSame('target', $state['pendingMatchEnd']['reason'] ?? null);

        $state = $this->scoring->updateMeta($state, [
            'pendingMatchEnd' => null,
            'pendingMatchEndDismissed' => true,
        ]);
        $this->assertNull($state['pendingMatchEnd']);
        $this->assertTrue($state['pendingMatchEndDismissed']);

        $state = $this->scoring->scoreRuns($state, 1); // still above target
        $this->assertNull($state['pendingMatchEnd']);
        $this->assertSame('live', $state['matchStatus']);
        $this->assertSame('', trim((string) ($state['result'] ?? '')));
    }

    public function test_non_empty_result_alone_does_not_mean_completed(): void
    {
        $state = $this->baseState();
        $state['result'] = 'stale text';
        $state['matchStatus'] = 'live';
        $state = $this->scoring->normalizeMatchStatus($state);
        $this->assertSame('live', $state['matchStatus']);
        $this->assertFalse($this->scoring->isMatchCompleted($state));
    }
}
