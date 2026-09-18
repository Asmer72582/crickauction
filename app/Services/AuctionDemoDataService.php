<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\PlayerRegistration;
use App\Models\RegistrationForm;
use App\Models\Tournament;
use App\Support\CricketStatsCalculator;
use Illuminate\Support\Str;

class AuctionDemoDataService
{
    public const DEMO_AUCTION_NAME = 'Demo Auction';

    public function __construct(
        protected RegistrationFormService $forms,
        protected AuctionService $auctions
    ) {}

    /**
     * @return array{players: int, teams: int, auction: Auction, created_players: int}
     */
    public function seedForTournament(Tournament $tournament): array
    {
        $form = $this->forms->getOrCreateForTournament($tournament);
        $auction = Auction::where('tournament_id', $tournament->id)
            ->where('name', self::DEMO_AUCTION_NAME)
            ->first();

        if (! $auction) {
            $auction = $this->auctions->createDraft($tournament, $form, self::DEMO_AUCTION_NAME);
        }

        $auctionForm = $auction->registrationForm ?? $form;
        $createdPlayers = $this->ensureDemoPlayers($auctionForm);
        $approved = $auctionForm->registrations()->where('status', 'approved')->get();

        $this->auctions->syncTeams($auction, $this->demoTeams());

        if ($approved->isNotEmpty()) {
            $this->auctions->addPlayersFromRegistrations(
                $auction,
                $approved->pluck('id')->all(),
                (int) ($auction->rules['default_base_price'] ?? 20000)
            );
        }

        if ($auction->status === 'draft' && $auction->players()->count() >= 1 && $auction->teams()->count() >= 2) {
            $this->auctions->publish($auction);
        }

        $auction = $auction->fresh(['teams', 'players']);

        return [
            'players' => $approved->count(),
            'teams' => $auction->teams()->count(),
            'auction' => $auction,
            'created_players' => $createdPlayers,
        ];
    }

    protected function ensureDemoPlayers(RegistrationForm $form): int
    {
        $existing = $form->registrations()->where('status', 'approved')->count();
        if ($existing >= 12) {
            return 0;
        }

        $created = 0;
        $seq = $form->registrations()->count();

        foreach ($this->demoPlayerTemplates() as $template) {
            $name = $template['full_name'];
            $already = $form->registrations()
                ->where('data->full_name', $name)
                ->exists();
            if ($already) {
                continue;
            }

            $seq++;
            $code = $this->demoRegistrationCode($form, $seq);
            $stats = CricketStatsCalculator::compute($template);

            PlayerRegistration::create([
                'registration_form_id' => $form->id,
                'form_version' => $form->current_version,
                'registration_code' => $code,
                'data' => array_merge($template, $stats),
                'status' => 'approved',
                'submitted_at' => now()->subDays(rand(1, 14)),
                'reviewed_at' => now(),
            ]);
            $created++;
        }

        return $created;
    }

    /** @return list<array<string, mixed>> */
    protected function demoTeams(): array
    {
        return [
            ['name' => 'Mumbai Strikers', 'short_name' => 'MUM', 'owner' => 'Demo Owner 1'],
            ['name' => 'Delhi Warriors', 'short_name' => 'DEL', 'owner' => 'Demo Owner 2'],
            ['name' => 'Chennai Kings', 'short_name' => 'CHE', 'owner' => 'Demo Owner 3'],
            ['name' => 'Bengaluru Royals', 'short_name' => 'BLR', 'owner' => 'Demo Owner 4'],
            ['name' => 'Kolkata Tigers', 'short_name' => 'KOL', 'owner' => 'Demo Owner 5'],
            ['name' => 'Hyderabad Suns', 'short_name' => 'HYD', 'owner' => 'Demo Owner 6'],
        ];
    }

    /** @return list<array<string, mixed>> */
    protected function demoPlayerTemplates(): array
    {
        return [
            $this->player('Rohit Mehta', 'Batter', 'Right Hand', null, 45, 1280, 156, 0),
            $this->player('Virat Singh', 'Batter', 'Right Hand', null, 52, 1890, 178, 2),
            $this->player('Shubham Gill', 'Batter', 'Right Hand', null, 38, 1120, 142, 0),
            $this->player('KL Rahul', 'Batter, Wicketkeeper', 'Right Hand', null, 40, 1450, 165, 0),
            $this->player('Hardik Pandya', 'All-rounder', 'Right Hand', 'Right Arm Medium', 48, 980, 134, 38),
            $this->player('Ravindra Jadeja', 'All-rounder', 'Left Hand', 'Left Arm Spin', 55, 720, 98, 52),
            $this->player('Jasprit Bumrah', 'Bowler', 'Right Hand', 'Right Arm Fast', 42, 180, 45, 68),
            $this->player('Mohammed Shami', 'Bowler', 'Right Hand', 'Right Arm Fast', 50, 220, 38, 82),
            $this->player('Yuzvendra Chahal', 'Bowler', 'Right Hand', 'Right Arm Spin', 44, 95, 22, 58),
            $this->player('Rishabh Pant', 'Batter, Wicketkeeper', 'Left Hand', null, 35, 890, 128, 0),
            $this->player('Suryakumar Yadav', 'Batter', 'Right Hand', null, 36, 1050, 145, 0),
            $this->player('Arshdeep Singh', 'Bowler', 'Left Hand', 'Left Arm Medium', 30, 120, 35, 42),
            $this->player('Axar Patel', 'All-rounder', 'Left Hand', 'Left Arm Spin', 41, 560, 78, 48),
            $this->player('Ishan Kishan', 'Batter, Wicketkeeper', 'Left Hand', null, 33, 760, 118, 0),
            $this->player('Kuldeep Yadav', 'Bowler', 'Left Hand', 'Left Arm Spin', 39, 140, 28, 55),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function player(
        string $name,
        string $role,
        string $batting,
        ?string $bowling,
        int $matches,
        int $runs,
        int $highest,
        int $wickets
    ): array {
        return [
            'full_name' => $name,
            'mobile' => '98'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'email' => Str::slug($name, '.').'@demo.cricket',
            'city' => collect(['Mumbai', 'Delhi', 'Chennai', 'Bengaluru', 'Kolkata', 'Pune'])->random(),
            'state' => 'Maharashtra',
            'playing_role' => $role,
            'batting_style' => $batting,
            'bowling_style' => $bowling ?? '',
            'experience' => rand(3, 12).' years',
            'matches' => (string) $matches,
            'runs' => (string) $runs,
            'highest_score' => (string) $highest,
            'wickets' => (string) $wickets,
            'requested_base_price' => (string) (rand(1, 4) * 10000),
        ];
    }

    protected function demoRegistrationCode(RegistrationForm $form, int $seq): string
    {
        $prefix = strtoupper(Str::limit(Str::slug($form->tournament->name, ''), 2, ''));
        if (strlen($prefix) < 2) {
            $prefix = 'DM';
        }

        return sprintf('%s-%s-%05d', $prefix, now()->format('Y'), $seq);
    }
}
