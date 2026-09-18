<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionEvent;
use App\Models\AuctionPlayer;
use App\Models\AuctionTeam;
use App\Models\PlayerRegistration;
use App\Models\RegistrationForm;
use App\Models\Tournament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuctionService
{
    public function __construct(
        protected RealtimePublisher $realtime,
        protected RegistrationFormService $forms
    ) {}

    public function ensureStandaloneHost(): Tournament
    {
        return Tournament::firstOrCreate(
            ['slug' => Tournament::STANDALONE_SLUG],
            [
                'name' => 'Standalone Auctions',
                'sport' => 'Cricket',
                'type' => 'Auction Host',
                'theme_id' => 'arena',
                'room_id' => 'standalone-auctions',
                'wickets' => 10,
                'groups' => 1,
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addYears(10)->toDateString(),
                'status' => 'active',
                'assigned_to' => 'System',
                'theme_charge' => 0,
            ]
        );
    }

    public function createDraft(Tournament $tournament, RegistrationForm $form, string $name): Auction
    {
        if ($form->tournament_id !== $tournament->id) {
            throw ValidationException::withMessages(['form' => 'Registration form does not belong to this tournament.']);
        }

        return DB::transaction(function () use ($tournament, $form, $name) {
            $dedicated = $this->forms->createForAuction(
                $tournament,
                $name.' Player Registration',
                $form
            );

            $auction = Auction::create([
                'tournament_id' => $tournament->id,
                'registration_form_id' => $dedicated->id,
                'name' => $name,
                'status' => 'draft',
                'room_id' => 'auction-'.$tournament->id.'-'.Str::lower(Str::random(6)),
                'public_token' => Str::random(48),
                'rules' => $this->defaultRules(),
                'live_state' => $this->defaultLiveState(),
                'version' => 0,
            ]);

            if (Schema::hasColumn('registration_forms', 'auction_id')) {
                $dedicated->update([
                    'auction_id' => $auction->id,
                    'status' => 'open',
                ]);
            }

            return $auction;
        });
    }

    public function defaultRules(): array
    {
        return [
            'team_count' => 8,
            'starting_purse' => 5000000,
            'min_squad' => 11,
            'max_squad' => 25,
            'default_base_price' => 20000,
            'bid_increment' => 10000,
            'bid_timer_seconds' => 30,
            'bid_extension_seconds' => 10,
            'allow_reauction' => true,
        ];
    }

    public function defaultLiveState(): array
    {
        return [
            'currentBid' => 0,
            'highestBidderId' => null,
            'bidHistory' => [],
            'paused' => false,
        ];
    }

    public function addPlayersFromRegistrations(Auction $auction, array $registrationIds, int $defaultBasePrice = 0): int
    {
        $rules = $auction->rules ?? [];
        $base = $defaultBasePrice ?: (int) ($rules['default_base_price'] ?? 20000);

        $approved = PlayerRegistration::where('registration_form_id', $auction->registration_form_id)
            ->where('status', 'approved')
            ->whereIn('id', $registrationIds)
            ->get();

        $maxOrder = (int) $auction->players()->max('sort_order');
        $added = 0;

        foreach ($approved as $reg) {
            $exists = $auction->players()->where('player_registration_id', $reg->id)->exists();
            if ($exists) {
                continue;
            }

            $requested = (int) ($reg->data['requested_base_price'] ?? 0);
            $auctionBase = $base;
            if ($requested > 0) {
                $auctionBase = $requested;
            }

            $maxOrder++;
            AuctionPlayer::create([
                'auction_id' => $auction->id,
                'player_registration_id' => $reg->id,
                'category' => $reg->displayRole(),
                'base_price' => $auctionBase,
                'sort_order' => $maxOrder,
                'status' => 'pool',
            ]);
            $added++;
        }

        return $added;
    }

    public function syncTeams(Auction $auction, array $teams): void
    {
        $auction->teams()->delete();
        $rules = $auction->rules ?? [];
        $purse = (int) ($rules['starting_purse'] ?? 5000000);
        $min = (int) ($rules['min_squad'] ?? 11);
        $max = (int) ($rules['max_squad'] ?? 25);

        foreach ($teams as $i => $team) {
            if (empty($team['name'])) {
                continue;
            }
            $row = [
                'auction_id' => $auction->id,
                'name' => $team['name'],
                'short_name' => $team['short_name'] ?? Str::upper(Str::limit($team['name'], 3, '')),
                'logo' => $team['logo'] ?? null,
                'owner' => $team['owner'] ?? null,
                'manager' => $team['city'] ?? $team['manager'] ?? null,
                'starting_purse' => (int) ($team['starting_purse'] ?? $purse),
                'spent_purse' => 0,
                'min_squad' => (int) ($team['min_squad'] ?? $min),
                'max_squad' => (int) ($team['max_squad'] ?? $max),
                'sort_order' => $i,
            ];
            if ($this->teamHasCityColumn()) {
                $row['city'] = $team['city'] ?? null;
            }
            AuctionTeam::create($row);
        }
    }

    public function applySquadRules(Auction $auction): void
    {
        $rules = $auction->rules ?? [];
        $min = (int) ($rules['min_squad'] ?? 11);
        $max = (int) ($rules['max_squad'] ?? 25);
        $auction->teams()->update([
            'min_squad' => $min,
            'max_squad' => $max,
        ]);
    }

    public function updateTeamPurse(AuctionTeam $team, ?int $startingPurse = null, ?int $remaining = null): AuctionTeam
    {
        if ($remaining !== null) {
            $startingPurse = (int) $team->spent_purse + max(0, $remaining);
        }
        if ($startingPurse !== null) {
            $team->update(['starting_purse' => max((int) $team->spent_purse, $startingPurse)]);
        }

        return $team->fresh();
    }

    protected function teamHasCityColumn(): bool
    {
        static $hasCity = null;
        if ($hasCity === null) {
            $hasCity = Schema::hasColumn('auction_teams', 'city');
        }

        return $hasCity;
    }

    public function publish(Auction $auction): Auction
    {
        if ($auction->players()->count() < 1) {
            throw ValidationException::withMessages(['players' => 'Select at least one approved player.']);
        }
        if ($auction->teams()->count() < 2) {
            throw ValidationException::withMessages(['teams' => 'Add at least two teams.']);
        }

        $auction->update(['status' => 'published']);

        return $auction->fresh();
    }

    public function reorderQueue(Auction $auction, array $playerIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $playerIds)));
        $allowed = $auction->players()
            ->whereIn('status', ['pool', 'current'])
            ->pluck('id')
            ->all();
        $allowedLookup = array_flip($allowed);
        $order = 1;
        foreach ($ids as $id) {
            if (! isset($allowedLookup[$id])) {
                continue;
            }
            $auction->players()->where('id', $id)->update(['sort_order' => $order]);
            $order++;
        }
        $this->broadcast($auction->fresh());
    }

    public function start(Auction $auction): Auction
    {
        if (! in_array($auction->status, ['published', 'paused'], true)) {
            throw ValidationException::withMessages(['auction' => 'Auction cannot be started.']);
        }

        $auction->update([
            'status' => 'live',
            'started_at' => $auction->started_at ?? now(),
        ]);

        $this->logEvent($auction, 'AUCTION_RESUMED', []);
        $this->broadcast($auction);

        return $auction->fresh();
    }

    public function pause(Auction $auction): Auction
    {
        if ($auction->status !== 'live') {
            throw ValidationException::withMessages(['auction' => 'Only a live auction can be paused.']);
        }

        $auction->update(['status' => 'paused']);
        $this->logEvent($auction, 'AUCTION_PAUSED', []);
        $this->broadcast($auction);

        return $auction->fresh();
    }

    public function resume(Auction $auction): Auction
    {
        return $this->start($auction);
    }

    public function nextPlayer(Auction $auction): ?AuctionPlayer
    {
        $current = $auction->players()->where('status', 'current')->first();
        if ($current) {
            $current->update(['status' => 'pool']);
        }

        $next = $auction->players()
            ->where('status', 'pool')
            ->orderBy('sort_order')
            ->first();

        if (! $next) {
            $auction->update(['current_auction_player_id' => null]);
            $this->resetBiddingState($auction);
            $this->logEvent($auction, 'NEXT_PLAYER', ['player' => null]);
            $this->broadcast($auction);

            return null;
        }

        $next->update(['status' => 'current']);
        $auction->update(['current_auction_player_id' => $next->id]);
        $this->resetBiddingState($auction);

        $this->logEvent($auction, 'PLAYER_SELECTED', $this->playerPayload($next));
        $this->logEvent($auction, 'BIDDING_STARTED', []);
        $this->broadcast($auction, 'PLAYER_SELECTED');

        return $next->fresh(['registration.files']);
    }

    public function placeBid(Auction $auction, AuctionTeam $team, int $amount): AuctionBid
    {
        if ($auction->status !== 'live') {
            throw ValidationException::withMessages(['auction' => 'Auction is not live.']);
        }

        $player = $auction->currentPlayer;
        if (! $player || $player->status !== 'current') {
            throw ValidationException::withMessages(['player' => 'No player on the block.']);
        }

        $rules = $auction->rules ?? [];
        $increment = (int) ($rules['bid_increment'] ?? 10000);
        $live = $auction->live_state ?? [];
        $currentBid = (int) ($live['currentBid'] ?? 0);
        $minBid = $currentBid > 0 ? $currentBid + $increment : (int) $player->base_price;

        if ($amount < $minBid) {
            throw ValidationException::withMessages(['amount' => "Minimum bid is ₹".number_format($minBid)]);
        }

        if ($amount > $team->remainingPurse()) {
            throw ValidationException::withMessages(['amount' => 'Insufficient team purse.']);
        }

        $squadCount = $team->squad()->count();
        if ($squadCount >= $team->max_squad) {
            throw ValidationException::withMessages(['team' => 'Team squad is full.']);
        }

        $bid = AuctionBid::create([
            'auction_player_id' => $player->id,
            'auction_team_id' => $team->id,
            'amount' => $amount,
        ]);

        $history = $live['bidHistory'] ?? [];
        $history[] = [
            'teamId' => $team->id,
            'teamName' => $team->name,
            'teamLogo' => $team->logo,
            'amount' => $amount,
            'at' => now()->toIso8601String(),
        ];

        $auction->update([
            'live_state' => array_merge($live, [
                'currentBid' => $amount,
                'highestBidderId' => $team->id,
                'bidHistory' => $history,
                'bidTimerStartedAt' => now()->toIso8601String(),
            ]),
        ]);

        $this->logEvent($auction, 'BID_PLACED', [
            'teamId' => $team->id,
            'teamName' => $team->name,
            'teamLogo' => $team->logo,
            'amount' => $amount,
            'auctionPlayerId' => $player->id,
        ]);
        $this->broadcast($auction, 'BID_PLACED');

        return $bid;
    }

    public function undoLastBid(Auction $auction): array
    {
        if (! in_array($auction->status, ['live', 'paused'], true)) {
            throw ValidationException::withMessages(['auction' => 'Auction must be live or paused to undo a bid.']);
        }

        $player = $auction->currentPlayer;
        if (! $player || $player->status !== 'current') {
            throw ValidationException::withMessages(['player' => 'No player on the block.']);
        }

        $live = $auction->live_state ?? [];
        $history = array_values($live['bidHistory'] ?? []);
        if ($history === []) {
            throw ValidationException::withMessages(['bid' => 'No bids to undo.']);
        }

        $removed = array_pop($history);
        $previous = $history !== [] ? $history[array_key_last($history)] : null;

        $lastDbBid = AuctionBid::query()
            ->where('auction_player_id', $player->id)
            ->orderByDesc('id')
            ->first();

        if ($lastDbBid) {
            $lastDbBid->delete();
        }

        $auction->update([
            'live_state' => array_merge($live, [
                'currentBid' => $previous ? (int) $previous['amount'] : 0,
                'highestBidderId' => $previous['teamId'] ?? null,
                'bidHistory' => $history,
                'bidTimerStartedAt' => now()->toIso8601String(),
            ]),
        ]);

        $this->logEvent($auction, 'BID_UNDONE', [
            'removed' => $removed,
            'auctionPlayerId' => $player->id,
            'restoredAmount' => $previous['amount'] ?? 0,
            'restoredTeamId' => $previous['teamId'] ?? null,
        ]);
        $this->broadcast($auction, 'BID_UNDONE');

        return [
            'removed' => $removed,
            'currentBid' => $previous ? (int) $previous['amount'] : 0,
            'highestBidderId' => $previous['teamId'] ?? null,
        ];
    }

    public function markSold(Auction $auction): AuctionPlayer
    {
        $player = $auction->currentPlayer;
        if (! $player || $player->status !== 'current') {
            throw ValidationException::withMessages(['player' => 'No player on the block.']);
        }

        $live = $auction->live_state ?? [];
        $amount = (int) ($live['currentBid'] ?? 0);
        $teamId = $live['highestBidderId'] ?? null;

        if ($amount < 1 || ! $teamId) {
            throw ValidationException::withMessages(['bid' => 'No valid bid to sell at.']);
        }

        $team = AuctionTeam::where('auction_id', $auction->id)->findOrFail($teamId);

        if ($amount > $team->remainingPurse()) {
            throw ValidationException::withMessages(['bid' => 'Team cannot afford this bid.']);
        }

        $player->update([
            'status' => 'sold',
            'sold_price' => $amount,
            'auction_team_id' => $team->id,
        ]);

        $team->increment('spent_purse', $amount);

        $payload = array_merge($this->playerPayload($player->fresh(['registration.files', 'soldTeam'])), [
            'soldPrice' => $amount,
            'soldTeamId' => $team->id,
            'soldTeamName' => $team->name,
            'teamLogo' => $team->logo,
        ]);

        $auction->update(['current_auction_player_id' => null]);
        $this->resetBiddingState($auction);
        $this->setLastEvent($auction, 'SOLD', $payload);

        $this->logEvent($auction, 'SOLD', $payload);
        $this->broadcast($auction, 'SOLD', $payload);

        return $player->fresh(['registration', 'soldTeam']);
    }

    public function markUnsold(Auction $auction): AuctionPlayer
    {
        $player = $auction->currentPlayer;
        if (! $player || $player->status !== 'current') {
            throw ValidationException::withMessages(['player' => 'No player on the block.']);
        }

        $payload = $this->playerPayload($player);

        $player->update([
            'status' => 'unsold',
            'sold_price' => null,
            'auction_team_id' => null,
        ]);

        $auction->update(['current_auction_player_id' => null]);
        $this->resetBiddingState($auction);
        $this->setLastEvent($auction, 'UNSOLD', $payload);

        $this->logEvent($auction, 'UNSOLD', $payload);
        $this->broadcast($auction, 'UNSOLD', $payload);

        return $player->fresh(['registration']);
    }

    protected function setLastEvent(Auction $auction, string $type, array $payload): void
    {
        $live = $auction->fresh()->live_state ?? [];
        $auction->update([
            'live_state' => array_merge($live, [
                'lastEvent' => [
                    'type' => $type,
                    'payload' => $payload,
                    'at' => now()->toIso8601String(),
                ],
            ]),
        ]);
    }

    public function complete(Auction $auction): Auction
    {
        $auction->update([
            'status' => 'completed',
            'completed_at' => now(),
            'current_auction_player_id' => null,
        ]);
        $this->broadcast($auction);

        return $auction->fresh();
    }

    protected function resetBiddingState(Auction $auction): void
    {
        $live = $auction->live_state ?? [];
        $auction->update([
            'live_state' => array_merge($live, [
                'currentBid' => 0,
                'highestBidderId' => null,
                'bidHistory' => [],
                'bidTimerStartedAt' => now()->toIso8601String(),
            ]),
        ]);
    }

    public function playerPayload(AuctionPlayer $ap): array
    {
        $reg = $ap->registration;
        $data = $reg?->data ?? [];
        $role = $reg?->displayRole() ?? '';

        return [
            'auctionId' => $ap->auction_id,
            'auctionPlayerId' => $ap->id,
            'registeredPlayerId' => $ap->player_registration_id,
            'registrationCode' => $reg?->registration_code,
            'playerName' => $reg?->displayName(),
            'firstName' => $this->splitName($reg?->displayName())['first'],
            'lastName' => $this->splitName($reg?->displayName())['last'],
            'playerPhoto' => $reg?->photoUrl(),
            'role' => $role,
            'primaryRole' => $this->primaryRoleLabel($role),
            'battingStyle' => $data['batting_style'] ?? null,
            'bowlingStyle' => $data['bowling_style'] ?? null,
            'styleLine' => $this->styleLine($data),
            'age' => $this->ageFromDob($data['date_of_birth'] ?? null),
            'statistics' => [
                'matches' => $data['matches'] ?? null,
                'runs' => $data['runs'] ?? null,
                'wickets' => $data['wickets'] ?? null,
                'average' => $data['average'] ?? null,
                'strikeRate' => $data['strike_rate'] ?? null,
                'highestScore' => $data['highest_score'] ?? null,
                'economy' => $data['economy'] ?? null,
                'bestBowling' => $data['best_bowling'] ?? null,
            ],
            'category' => $ap->category,
            'basePrice' => (int) $ap->base_price,
            'sortOrder' => (int) $ap->sort_order,
            'auctionStatus' => $ap->status,
            'soldPrice' => $ap->sold_price !== null ? (int) $ap->sold_price : null,
            'soldTeamId' => $ap->auction_team_id,
            'soldTeamName' => $ap->soldTeam?->name,
            'teamLogo' => $ap->soldTeam?->logo,
        ];
    }

    /** @return array{first: string, last: string} */
    protected function splitName(?string $name): array
    {
        $name = trim((string) $name);
        if ($name === '') {
            return ['first' => '—', 'last' => ''];
        }
        $parts = preg_split('/\s+/', $name) ?: [];
        if (count($parts) === 1) {
            return ['first' => strtoupper($parts[0]), 'last' => ''];
        }
        $last = array_pop($parts);

        return [
            'first' => strtoupper(implode(' ', $parts)),
            'last' => strtoupper((string) $last),
        ];
    }

    protected function ageFromDob(mixed $dob): ?int
    {
        if (! $dob) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse((string) $dob)->age;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function primaryRoleLabel(string $role): string
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $role))));
        if (! $parts) {
            return 'PLAYER';
        }
        $first = strtoupper($parts[0]);
        if (str_contains(strtolower($parts[0]), 'all')) {
            return 'ALL ROUNDER';
        }
        if (str_contains(strtolower($parts[0]), 'wicket')) {
            return 'WICKET KEEPER';
        }

        return $first;
    }

    protected function styleLine(array $data): string
    {
        $bat = trim((string) ($data['batting_style'] ?? ''));
        $bowl = trim((string) ($data['bowling_style'] ?? ''));
        if ($bat && $bowl) {
            return strtoupper($bat.' · '.$bowl);
        }
        if ($bat) {
            return strtoupper($bat.($bat && ! str_contains(strtolower($bat), 'bat') ? ' BAT' : ''));
        }
        if ($bowl) {
            return strtoupper($bowl);
        }

        return '';
    }

    public function overlayState(Auction $auction): array
    {
        $auction->load([
            'tournament',
            'currentPlayer.registration.files',
            'teams',
            'players.registration.files',
            'players.soldTeam',
        ]);
        $live = $auction->live_state ?? [];
        $rules = $auction->rules ?? [];
        $current = $auction->currentPlayer;
        $highestTeam = null;
        if (! empty($live['highestBidderId'])) {
            $highestTeam = $auction->teams->firstWhere('id', (int) $live['highestBidderId']);
        }

        $currentBid = (int) ($live['currentBid'] ?? 0);
        $basePrice = (int) ($current?->base_price ?? 0);
        $increment = (int) ($rules['bid_increment'] ?? 10000);
        $nextMin = $currentBid > 0 ? $currentBid + $increment : $basePrice;

        $teams = $auction->teams->map(function (AuctionTeam $t) use ($auction, $rules) {
            $squad = $auction->players
                ->where('auction_team_id', $t->id)
                ->where('status', 'sold')
                ->values()
                ->map(fn (AuctionPlayer $p) => $this->squadMemberPayload($p))
                ->all();

            $roleCounts = [
                'batter' => 0,
                'allrounder' => 0,
                'bowler' => 0,
                'wicketkeeper' => 0,
            ];
            foreach ($squad as $p) {
                $key = $this->roleBucket($p['role'] ?? '');
                $roleCounts[$key]++;
            }

            return [
                'id' => $t->id,
                'name' => $t->name,
                'shortName' => $t->short_name,
                'logo' => $t->logo,
                'owner' => $t->owner,
                'city' => $t->city ?: $t->manager,
                'manager' => $t->manager,
                'startingPurse' => (int) $t->starting_purse,
                'spent' => (int) $t->spent_purse,
                'remaining' => $t->remainingPurse(),
                'squadCount' => count($squad),
                'maxSquad' => (int) ($t->max_squad ?: ($rules['max_squad'] ?? 25)),
                'minSquad' => (int) ($t->min_squad ?: ($rules['min_squad'] ?? 11)),
                'squad' => $squad,
                'roleCounts' => $roleCounts,
                'spentPct' => $t->starting_purse > 0
                    ? (int) round(($t->spent_purse / $t->starting_purse) * 100)
                    : 0,
            ];
        })->values()->all();

        return [
            'auctionId' => $auction->id,
            'version' => (int) $auction->version,
            'auctionName' => $auction->name,
            'tournamentName' => $auction->tournament?->name ?? 'PLAYER AUCTION',
            'year' => now()->format('Y'),
            'status' => $auction->status,
            'currentBid' => $currentBid,
            'bidIncrement' => $increment,
            'nextMinBid' => $nextMin,
            'highestBidder' => $highestTeam?->name,
            'highestBidderId' => $live['highestBidderId'] ?? null,
            'highestBidderLogo' => $highestTeam?->logo,
            'bidHistory' => array_values(array_reverse(array_slice($live['bidHistory'] ?? [], -8))),
            'bidTimerSeconds' => (int) ($rules['bid_timer_seconds'] ?? 30),
            'bidTimerStartedAt' => $live['bidTimerStartedAt'] ?? null,
            'broadcastView' => $live['broadcastView'] ?? 'player',
            'broadcastTeamId' => $live['broadcastTeamId'] ?? null,
            'rules' => [
                'bid_increment' => $increment,
                'bid_timer_seconds' => (int) ($rules['bid_timer_seconds'] ?? 30),
                'default_base_price' => (int) ($rules['default_base_price'] ?? 20000),
                'custom_increments' => $rules['custom_increments'] ?? null,
                'league_title' => $rules['league_title'] ?? null,
                'sponsor_line' => $rules['sponsor_line'] ?? null,
                'min_squad' => (int) ($rules['min_squad'] ?? 11),
                'max_squad' => (int) ($rules['max_squad'] ?? 25),
            ],
            'teams' => $teams,
            'queue' => $auction->players
                ->sortBy('sort_order')
                ->values()
                ->map(fn (AuctionPlayer $p) => [
                    'auctionPlayerId' => $p->id,
                    'playerName' => $p->registration?->displayName(),
                    'role' => $p->registration?->displayRole() ?: $p->category,
                    'primaryRole' => $this->primaryRoleLabel($p->registration?->displayRole() ?: (string) $p->category),
                    'basePrice' => (int) $p->base_price,
                    'soldPrice' => $p->sold_price !== null ? (int) $p->sold_price : null,
                    'soldTeamName' => $p->soldTeam?->name,
                    'status' => $p->status,
                    'sortOrder' => (int) $p->sort_order,
                ])
                ->all(),
            'currentPlayer' => $current ? array_merge($this->playerPayload($current), [
                'currentBid' => $currentBid,
                'highestBidder' => $highestTeam?->name,
                'teamLogo' => $highestTeam?->logo,
            ]) : null,
            'lastEvent' => $live['lastEvent'] ?? null,
            'summary' => [
                'totalSelected' => $auction->players->count(),
                'sold' => $auction->players->where('status', 'sold')->count(),
                'unsold' => $auction->players->where('status', 'unsold')->count(),
                'pool' => $auction->players->where('status', 'pool')->count(),
            ],
        ];
    }

    protected function roleBucket(string $role): string
    {
        $r = strtolower($role);
        if (str_contains($r, 'wicket') || str_contains($r, 'keeper')) {
            return 'wicketkeeper';
        }
        if (str_contains($r, 'all')) {
            return 'allrounder';
        }
        if (str_contains($r, 'bowl')) {
            return 'bowler';
        }

        return 'batter';
    }

    public function broadcast(Auction $auction, ?string $animation = null, ?array $animPayload = null): array
    {
        $auction->increment('version');
        $fresh = $auction->fresh([
            'tournament',
            'currentPlayer.registration.files',
            'teams',
            'players.registration.files',
            'players.soldTeam',
        ]);
        $state = $this->overlayState($fresh);

        $anim = null;
        if ($animation) {
            $anim = [
                'animation' => $animation,
                'payload' => $this->compactAnimationPayload($animPayload ?? [], $state),
            ];
        }

        $this->realtime->publish((string) $fresh->room_id, $state, (int) $fresh->version, $anim);

        return $state;
    }

    /** Compact sold-player row for overlays / owner portal (no full statistics). */
    protected function squadMemberPayload(AuctionPlayer $p): array
    {
        $reg = $p->registration;
        $role = $reg?->displayRole() ?: (string) $p->category;

        return [
            'auctionPlayerId' => $p->id,
            'playerName' => $reg?->displayName(),
            'playerPhoto' => $reg?->photoUrl(),
            'role' => $role,
            'primaryRole' => $this->primaryRoleLabel($role),
            'soldPrice' => $p->sold_price !== null ? (int) $p->sold_price : null,
            'auctionStatus' => $p->status,
        ];
    }

    /** Small animation payload so Pusher events stay under the 10KB cap. */
    protected function compactAnimationPayload(array $payload, array $state): array
    {
        $p = $payload ?: (is_array($state['currentPlayer'] ?? null) ? $state['currentPlayer'] : []);

        return [
            'auctionPlayerId' => $p['auctionPlayerId'] ?? null,
            'playerName' => $p['playerName'] ?? null,
            'firstName' => $p['firstName'] ?? null,
            'lastName' => $p['lastName'] ?? null,
            'playerPhoto' => $p['playerPhoto'] ?? null,
            'amount' => $p['soldPrice'] ?? $p['amount'] ?? $state['currentBid'] ?? 0,
            'currentBid' => $p['currentBid'] ?? $state['currentBid'] ?? 0,
            'soldPrice' => $p['soldPrice'] ?? null,
            'soldTeamName' => $p['soldTeamName'] ?? $state['highestBidder'] ?? null,
            'teamName' => $p['soldTeamName'] ?? $p['teamName'] ?? $state['highestBidder'] ?? null,
            'highestBidder' => $p['highestBidder'] ?? $state['highestBidder'] ?? null,
            'teamLogo' => $p['teamLogo'] ?? $state['highestBidderLogo'] ?? null,
            'basePrice' => $p['basePrice'] ?? null,
        ];
    }

    protected function logEvent(Auction $auction, string $type, array $payload): void
    {
        AuctionEvent::create([
            'auction_id' => $auction->id,
            'type' => $type,
            'payload' => $payload,
        ]);
    }

    public function resultsSummary(Auction $auction): array
    {
        $players = $auction->players()->with(['registration', 'soldTeam'])->get();
        $teams = $auction->teams()->withCount(['squad'])->get();

        $sold = $players->where('status', 'sold');
        $prices = $sold->pluck('sold_price')->filter();

        return [
            'players' => $players->map(fn (AuctionPlayer $p) => [
                'name' => $p->registration?->displayName(),
                'registrationCode' => $p->registration?->registration_code,
                'basePrice' => $p->base_price,
                'soldPrice' => $p->sold_price,
                'team' => $p->soldTeam?->name,
                'status' => $p->status,
            ])->values(),
            'teams' => $teams->map(fn (AuctionTeam $t) => [
                'name' => $t->name,
                'players' => $t->squad_count,
                'spent' => $t->spent_purse,
                'remaining' => $t->remainingPurse(),
            ])->values(),
            'summary' => [
                'totalSelected' => $players->count(),
                'sold' => $sold->count(),
                'unsold' => $players->where('status', 'unsold')->count(),
                'totalSpent' => $sold->sum('sold_price'),
                'highestSale' => $prices->max() ?? 0,
                'averageSale' => $prices->count() ? (int) round($prices->avg()) : 0,
            ],
        ];
    }
}
