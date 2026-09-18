<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Services\AuctionDemoDataService;
use Illuminate\Console\Command;

class SeedAuctionDemoCommand extends Command
{
    protected $signature = 'auction:demo {tournament? : Tournament ID (defaults to first tournament)}';

    protected $description = 'Seed demo approved players, teams, and a published auction for testing';

    public function handle(AuctionDemoDataService $demo): int
    {
        $id = $this->argument('tournament');
        $tournament = $id
            ? Tournament::findOrFail($id)
            : Tournament::query()->orderBy('id')->first();

        if (! $tournament) {
            $this->error('No tournament found. Create a tournament first.');

            return self::FAILURE;
        }

        $result = $demo->seedForTournament($tournament);

        $this->info("Tournament: {$tournament->name}");
        $this->info("Created {$result['created_players']} new demo players");
        $this->info("Approved players in pool: {$result['players']}");
        $this->info("Teams: {$result['teams']}");
        $this->info("Auction: {$result['auction']->name} ({$result['auction']->status})");
        $this->line('Live URL: /tournaments/'.$tournament->id.'/auctions/'.$result['auction']->id.'/live');

        return self::SUCCESS;
    }
}
