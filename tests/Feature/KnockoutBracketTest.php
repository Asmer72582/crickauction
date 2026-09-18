<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\KnockoutBracketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KnockoutBracketTest extends TestCase
{
    use RefreshDatabase;

    protected function teams(): array
    {
        return [
            'India', 'Australia', 'England', 'Pakistan',
            'New Zealand', 'South Africa', 'West Indies', 'Sri Lanka',
            'Bangladesh', 'Afghanistan', 'Ireland', 'Scotland',
            'Netherlands', 'Zimbabwe', 'Nepal', 'USA',
        ];
    }

    protected function createKnockout(): Tournament
    {
        $tournament = Tournament::create([
            'name' => 'World Cup Knockout',
            'slug' => 'world-cup-ko-'.Str::lower(Str::random(4)),
            'sport' => 'Cricket',
            'type' => 'Knockout Tournament',
            'theme_id' => 'arena',
            'room_id' => 'ko-test-'.Str::lower(Str::random(6)),
            'wickets' => 10,
            'groups' => 1,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDays(14)->toDateString(),
            'status' => 'active',
            'assigned_to' => 'Operator',
            'theme_charge' => 0,
        ]);

        return app(KnockoutBracketService::class)->generateRoundOf16Bracket(
            $tournament,
            $this->teams()
        );
    }

    public function test_generates_full_15_match_bracket_with_correct_statuses(): void
    {
        $tournament = $this->createKnockout();
        $matches = $tournament->matches()->where('is_bracket', true)->orderBy('bracket_order')->get();

        $this->assertCount(15, $matches);
        $this->assertSame(16, (int) $tournament->bracket_size);

        $r16 = $matches->where('round_code', 'R16');
        $qf = $matches->where('round_code', 'QF');
        $sf = $matches->where('round_code', 'SF');
        $final = $matches->where('round_code', 'F');

        $this->assertCount(8, $r16);
        $this->assertCount(4, $qf);
        $this->assertCount(2, $sf);
        $this->assertCount(1, $final);

        foreach ($r16 as $m) {
            $this->assertSame(KnockoutBracketService::STATUS_READY, $m->status);
            $this->assertNotEmpty($m->team_a_name);
            $this->assertNotEmpty($m->team_b_name);
            $this->assertNotNull($m->next_match_id);
            $this->assertContains($m->next_slot, ['A', 'B']);
        }

        foreach ($qf->concat($sf)->concat($final) as $m) {
            $this->assertSame(KnockoutBracketService::STATUS_WAITING, $m->status);
            $this->assertTrue(in_array($m->team_a_name, [null, '', 'TBD'], true));
            $this->assertTrue(in_array($m->team_b_name, [null, '', 'TBD'], true));
            $this->assertStringStartsWith('Winner ', $m->displayTeamA());
            $this->assertStringStartsWith('Winner ', $m->displayTeamB());
        }

        $r16_1 = $matches->firstWhere('bracket_code', 'R16-1');
        $qf1 = $matches->firstWhere('bracket_code', 'QF-1');
        $this->assertSame($qf1->id, $r16_1->next_match_id);
        $this->assertSame('A', $r16_1->next_slot);
        $this->assertSame('India', $r16_1->team_a_name);
        $this->assertSame('Australia', $r16_1->team_b_name);
        $this->assertSame('Winner R16-1', $qf1->displayTeamA());
        $this->assertSame('Winner R16-2', $qf1->displayTeamB());
    }

    public function test_advancing_r16_winners_makes_qf_ready_without_creating_duplicates(): void
    {
        $tournament = $this->createKnockout();
        $ko = app(KnockoutBracketService::class);

        $r16_1 = TournamentMatch::where('tournament_id', $tournament->id)->where('bracket_code', 'R16-1')->firstOrFail();
        $r16_2 = TournamentMatch::where('tournament_id', $tournament->id)->where('bracket_code', 'R16-2')->firstOrFail();

        $ko->completeBracketMatchWithWinner($r16_1, 'A'); // India
        $qf1 = TournamentMatch::where('tournament_id', $tournament->id)->where('bracket_code', 'QF-1')->firstOrFail();
        $this->assertSame('India', $qf1->team_a_name);
        $this->assertTrue(in_array($qf1->team_b_name, [null, '', 'TBD'], true));
        $this->assertSame(KnockoutBracketService::STATUS_WAITING, $qf1->status);

        $beforeCount = TournamentMatch::where('tournament_id', $tournament->id)->where('is_bracket', true)->count();
        $ko->completeBracketMatchWithWinner($r16_2, 'B'); // Pakistan
        $afterCount = TournamentMatch::where('tournament_id', $tournament->id)->where('is_bracket', true)->count();
        $this->assertSame(15, $beforeCount);
        $this->assertSame(15, $afterCount);

        $qf1->refresh();
        $this->assertSame('India', $qf1->team_a_name);
        $this->assertSame('Pakistan', $qf1->team_b_name);
        $this->assertSame(KnockoutBracketService::STATUS_READY, $qf1->status);

        // Idempotent re-delivery
        $ko->completeBracketMatchWithWinner($r16_1, 'A');
        $qf1->refresh();
        $this->assertSame('India', $qf1->team_a_name);
        $this->assertSame(15, TournamentMatch::where('tournament_id', $tournament->id)->where('is_bracket', true)->count());
    }

    public function test_full_bracket_progression_to_champion(): void
    {
        $tournament = $this->createKnockout();
        $ko = app(KnockoutBracketService::class);

        // R16: always side A wins → India, England, NZ, WI, Bangladesh, Ireland, Netherlands, Nepal
        for ($i = 1; $i <= 8; $i++) {
            $m = TournamentMatch::where('tournament_id', $tournament->id)->where('bracket_code', "R16-{$i}")->firstOrFail();
            $ko->completeBracketMatchWithWinner($m, 'A');
        }

        for ($i = 1; $i <= 4; $i++) {
            $qf = TournamentMatch::where('tournament_id', $tournament->id)->where('bracket_code', "QF-{$i}")->firstOrFail();
            $this->assertSame(KnockoutBracketService::STATUS_READY, $qf->status, "QF-{$i} should be READY");
            $ko->completeBracketMatchWithWinner($qf, 'A');
        }

        for ($i = 1; $i <= 2; $i++) {
            $sf = TournamentMatch::where('tournament_id', $tournament->id)->where('bracket_code', "SF-{$i}")->firstOrFail();
            $this->assertSame(KnockoutBracketService::STATUS_READY, $sf->status);
            $ko->completeBracketMatchWithWinner($sf, 'A');
        }

        $final = TournamentMatch::where('tournament_id', $tournament->id)->where('bracket_code', 'FINAL')->firstOrFail();
        $this->assertSame(KnockoutBracketService::STATUS_READY, $final->status);
        $this->assertSame('India', $final->team_a_name);
        $this->assertSame('Bangladesh', $final->team_b_name);

        $tournament->refresh();
        $this->assertSame('active', $tournament->status);
        $this->assertNull($tournament->champion_name);

        $ko->completeBracketMatchWithWinner($final, 'A');

        $tournament->refresh();
        $final->refresh();
        $this->assertSame(KnockoutBracketService::STATUS_COMPLETED, $final->status);
        $this->assertSame('India', $final->winner_name);
        $this->assertSame('India', $tournament->champion_name);
        $this->assertSame('completed', $tournament->status);
        $this->assertNotNull($tournament->completed_at);
        $this->assertSame(15, TournamentMatch::where('tournament_id', $tournament->id)->where('is_bracket', true)->count());
    }

    public function test_tied_match_does_not_advance(): void
    {
        $tournament = $this->createKnockout();
        $rooms = app(\App\Services\MatchRoomService::class);
        $r16_1 = TournamentMatch::where('tournament_id', $tournament->id)->where('bracket_code', 'R16-1')->firstOrFail();
        $room = $rooms->getOrCreate($r16_1->room_id);
        $state = $room->state;
        $state['matchStatus'] = 'completed';
        $state['result'] = 'Match tied';
        $rooms->saveState($room, $state);

        $r16_1->refresh();
        $qf1 = TournamentMatch::where('tournament_id', $tournament->id)->where('bracket_code', 'QF-1')->firstOrFail();
        $this->assertTrue(in_array($qf1->team_a_name, [null, '', 'TBD'], true));
        $this->assertSame(KnockoutBracketService::STATUS_WAITING, $qf1->status);
        $this->assertNull($r16_1->winner_name);
    }

    public function test_dashboard_create_knockout_generates_bracket(): void
    {
        $payload = [
            'name' => 'API Knockout Cup',
            'sport' => 'Cricket',
            'type' => 'Knockout Tournament',
            'theme_id' => 'arena',
            'wickets' => 10,
            'teams' => $this->teams(),
            'r16_scheduled_at' => now()->format('Y-m-d H:i:s'),
        ];

        $res = $this->postJson('/tournaments', $payload);
        $res->assertCreated();
        $id = $res->json('tournament.id');
        $this->assertNotNull($id);
        $this->assertSame(15, TournamentMatch::where('tournament_id', $id)->where('is_bracket', true)->count());
    }
}
