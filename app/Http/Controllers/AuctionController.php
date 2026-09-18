<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\AuctionTeam;
use App\Models\PlayerRegistration;
use App\Models\RegistrationForm;
use App\Models\Tournament;
use App\Services\AuctionService;
use App\Services\OverlayThemeRegistry;
use App\Services\PlayerRegistrationService;
use App\Services\RegistrationFormService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuctionController extends Controller
{
    public function __construct(
        protected AuctionService $auctions,
        protected RegistrationFormService $forms,
        protected PlayerRegistrationService $registrations
    ) {}

    public function index()
    {
        $auctions = Auction::query()
            ->with(['tournament', 'registrationForm'])
            ->withCount(['players', 'teams'])
            ->latest()
            ->get()
            ->each(function (Auction $auction) {
                $form = $auction->registrationForm;
                $auction->pending_count = $form
                    ? $form->registrations()->where('status', 'pending')->count()
                    : 0;
                $auction->approved_count = $form
                    ? $form->registrations()->where('status', 'approved')->count()
                    : 0;
            });

        $tournaments = Tournament::query()->visible()->latest()->get();

        return view('auctions.index', [
            'auctions' => $auctions,
            'tournaments' => $tournaments,
        ]);
    }

    public function show(Request $request, Auction $auction)
    {
        $auction->load(['tournament', 'registrationForm', 'teams', 'players.registration']);
        $form = $auction->registrationForm;
        $tab = $request->query('tab', $auction->workflowPhase() === 'ready' ? 'start' : 'verify');

        $stats = [
            'total' => $form?->registrations()->count() ?? 0,
            'pending' => $form?->registrations()->where('status', 'pending')->count() ?? 0,
            'approved' => $form?->registrations()->where('status', 'approved')->count() ?? 0,
            'rejected' => $form?->registrations()->where('status', 'rejected')->count() ?? 0,
            'pool' => $auction->players()->count(),
            'teams' => $auction->teams()->count(),
        ];

        $registrations = null;
        if ($form && $tab === 'verify') {
            $query = $form->registrations()->with('files')->latest('submitted_at');
            if ($status = $request->query('status')) {
                $query->where('status', $status);
            }
            if ($search = trim((string) $request->query('q'))) {
                $query->where(function ($q) use ($search) {
                    $q->where('registration_code', 'like', "%{$search}%")
                        ->orWhere('data->full_name', 'like', "%{$search}%");
                });
            }
            $registrations = $query->paginate(40)->withQueryString();
        }

        return view('auctions.show', [
            'auction' => $auction,
            'tournament' => $auction->tournament,
            'form' => $form,
            'tab' => $tab,
            'stats' => $stats,
            'registrations' => $registrations,
            'registrationUrl' => $auction->registrationUrl(),
            'theme' => $auction->tournament?->theme() ?? OverlayThemeRegistry::get('arena'),
        ]);
    }

    public function update(Request $request, Auction $auction)
    {
        abort_if(in_array($auction->status, ['live', 'completed'], true), 422, 'Live or completed auctions cannot be edited here.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'rules.starting_purse' => ['nullable', 'integer', 'min:0'],
            'rules.default_base_price' => ['nullable', 'integer', 'min:0'],
            'rules.bid_increment' => ['nullable', 'integer', 'min:0'],
            'rules.bid_timer_seconds' => ['nullable', 'integer', 'min:5', 'max:300'],
            'rules.min_squad' => ['nullable', 'integer', 'min:1', 'max:40'],
            'rules.max_squad' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $auction->update([
            'name' => $data['name'],
            'rules' => array_merge($auction->rules ?? [], $data['rules'] ?? []),
        ]);

        if (isset($data['rules']['min_squad']) || isset($data['rules']['max_squad'])) {
            $this->auctions->applySquadRules($auction->fresh());
        }

        return back()->with('success', 'Auction details updated.');
    }

    public function updateTeam(Request $request, Auction $auction, AuctionTeam $team)
    {
        abort_unless($team->auction_id === $auction->id, 404);
        abort_if($auction->status === 'completed', 422, 'Cannot edit teams after the auction is completed.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'short_name' => ['nullable', 'string', 'max:12'],
            'owner' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'starting_purse' => ['nullable', 'integer', 'min:0'],
            'remaining' => ['nullable', 'integer', 'min:0'],
            'logo' => ['nullable', 'image', 'max:4096'],
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('auction-team-logos', 'public');
            $data['logo'] = '/storage/'.$path;
        }

        $remaining = array_key_exists('remaining', $data) ? $data['remaining'] : null;
        unset($data['remaining']);
        if (array_key_exists('city', $data)) {
            $data['manager'] = $data['city'];
            if (! \Illuminate\Support\Facades\Schema::hasColumn('auction_teams', 'city')) {
                unset($data['city']);
            }
        }

        $team->update(collect($data)->except(['starting_purse'])->all());
        if (array_key_exists('starting_purse', $data) || $remaining !== null) {
            $this->auctions->updateTeamPurse(
                $team->fresh(),
                isset($data['starting_purse']) ? (int) $data['starting_purse'] : null,
                $remaining !== null ? (int) $remaining : null
            );
        }

        if (in_array($auction->status, ['live', 'paused', 'published'], true)) {
            $this->auctions->broadcast($auction->fresh());
        }

        return redirect()
            ->route('auctions.show', ['auction' => $auction, 'tab' => 'teams'])
            ->with('success', 'Team updated.');
    }

    public function updatePoolPlayer(Request $request, Auction $auction, AuctionPlayer $player)
    {
        abort_unless($player->auction_id === $auction->id, 404);
        abort_if(in_array($auction->status, ['live', 'completed'], true), 422, 'Cannot edit pool while live or completed.');

        $data = $request->validate([
            'full_name' => ['nullable', 'string', 'max:200'],
            'playing_role' => ['nullable', 'string', 'max:120'],
            'base_price' => ['nullable', 'integer', 'min:0'],
        ]);

        if (array_key_exists('base_price', $data) && $data['base_price'] !== null) {
            $player->update(['base_price' => (int) $data['base_price']]);
        }

        $registration = $player->registration;
        if ($registration) {
            $payload = $registration->data ?? [];
            if (! empty($data['full_name'])) {
                $payload['full_name'] = $data['full_name'];
            }
            if (array_key_exists('playing_role', $data) && $data['playing_role'] !== null) {
                $payload['playing_role'] = $data['playing_role'];
                $player->update(['category' => $data['playing_role']]);
            }
            $registration->update(['data' => $payload]);
        }

        return redirect()
            ->route('auctions.show', ['auction' => $auction, 'tab' => 'teams'])
            ->with('success', 'Pool player updated.');
    }

    public function addPlayer(Request $request, Auction $auction)
    {
        abort_if(in_array($auction->status, ['live', 'completed'], true), 422);

        $form = $auction->registrationForm;
        abort_unless($form, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'role' => ['nullable', 'string', 'max:120'],
            'roles' => ['nullable', 'array', 'max:2'],
            'roles.*' => ['string', Rule::in(['Batter', 'Bowler', 'All-rounder', 'Wicketkeeper'])],
            'batting_style' => ['nullable', 'string', 'max:80'],
            'bowling_style' => ['nullable', 'string', 'max:80'],
            'base_price' => ['nullable', 'integer', 'min:0'],
            'matches' => ['nullable'],
            'runs' => ['nullable'],
            'highest_score' => ['nullable'],
            'wickets' => ['nullable'],
            'photo' => ['nullable', 'image', 'max:4096'],
            'add_to_pool' => ['nullable', 'boolean'],
        ]);

        $role = trim((string) ($data['role'] ?? ''));
        if ($role === '' && ! empty($data['roles'])) {
            $role = implode(', ', $data['roles']);
        }
        if ($role === '') {
            throw ValidationException::withMessages(['role' => 'Select at least one playing role.']);
        }

        $ids = $this->createManualRegistrations($form, [[
            'name' => $data['name'],
            'role' => $role,
            'batting_style' => $data['batting_style'] ?? null,
            'bowling_style' => $data['bowling_style'] ?? null,
            'base_price' => $data['base_price'] ?? null,
            'matches' => $data['matches'] ?? null,
            'runs' => $data['runs'] ?? null,
            'highest_score' => $data['highest_score'] ?? null,
            'wickets' => $data['wickets'] ?? null,
        ]], [
            0 => ['photo' => $request->file('photo')],
        ]);

        if ($request->boolean('add_to_pool', true)) {
            $this->auctions->addPlayersFromRegistrations(
                $auction,
                $ids,
                (int) (($auction->rules['default_base_price'] ?? 20000))
            );
        }

        return back()->with('success', 'Player added.');
    }

    public function approveRegistration(Request $request, Auction $auction, PlayerRegistration $registration)
    {
        $this->assertRegistrationBelongs($auction, $registration);
        $this->registrations->approve($registration, $request->input('notes'));

        if ($request->boolean('add_to_pool', true)) {
            $this->auctions->addPlayersFromRegistrations(
                $auction,
                [$registration->id],
                (int) ($auction->rules['default_base_price'] ?? 20000)
            );
        }

        return back()->with('success', 'Player approved.');
    }

    public function rejectRegistration(Request $request, Auction $auction, PlayerRegistration $registration)
    {
        $this->assertRegistrationBelongs($auction, $registration);
        $this->registrations->reject($registration, $request->input('notes'));

        return back()->with('success', 'Player rejected.');
    }

    public function syncApprovedPool(Auction $auction)
    {
        $form = $auction->registrationForm;
        abort_unless($form, 404);

        $ids = $form->registrations()->where('status', 'approved')->pluck('id')->all();
        $added = $this->auctions->addPlayersFromRegistrations(
            $auction,
            $ids,
            (int) ($auction->rules['default_base_price'] ?? 20000)
        );

        return back()->with('success', $added.' players synced into the auction pool.');
    }

    public function publishWorkspace(Auction $auction)
    {
        $this->auctions->publish($auction);

        return redirect()
            ->route('auctions.show', ['auction' => $auction, 'tab' => 'start'])
            ->with('success', 'Auction published. You can start the live desk.');
    }

    public function create(Tournament $tournament)
    {
        return redirect()->route('auctions.create', ['tournament' => $tournament->id]);
    }

    public function createStandalone(Request $request)
    {
        $selectedTournament = null;
        if ($request->filled('tournament')) {
            $selectedTournament = Tournament::findOrFail((int) $request->integer('tournament'));
        }

        return view('auction.create', $this->createViewData($selectedTournament));
    }

    public function store(Request $request, Tournament $tournament)
    {
        return $this->storeForTournament($request, $tournament);
    }

    public function storeStandalone(Request $request)
    {
        $tournament = null;
        if ($request->filled('tournament_id')) {
            $data = $request->validate([
                'tournament_id' => ['integer', Rule::exists('tournaments', 'id')],
            ]);
            $tournament = Tournament::findOrFail((int) $data['tournament_id']);
            if ($tournament->isStandaloneHost()) {
                $tournament = null;
            }
        }

        $host = $tournament ?: $this->auctions->ensureStandaloneHost();

        return $this->storeForTournament($request, $host, $tournament !== null);
    }

    public function pool(Tournament $tournament, Auction $auction)
    {
        abort_unless($auction->tournament_id === $tournament->id, 404);
        $auction->load(['players.registration', 'teams']);

        return view('auction.pool', [
            'tournament' => $tournament,
            'auction' => $auction,
            'theme' => $tournament->theme(),
        ]);
    }

    public function live(Tournament $tournament, Auction $auction)
    {
        abort_unless($auction->tournament_id === $tournament->id, 404);
        $auction->load(['teams', 'players.registration', 'currentPlayer.registration']);

        return view('auction.live', [
            'tournament' => $tournament,
            'auction' => $auction,
            'overlayUrl' => $auction->overlayUrl(),
            'ownerPortalUrl' => $auction->ownerPortalUrl(),
            'state' => $this->auctions->overlayState($auction),
            'theme' => $tournament->theme(),
        ]);
    }

    public function results(Tournament $tournament, Auction $auction)
    {
        abort_unless($auction->tournament_id === $tournament->id, 404);

        return view('auction.results', [
            'tournament' => $tournament,
            'auction' => $auction,
            'results' => $this->auctions->resultsSummary($auction),
            'theme' => $tournament->theme(),
        ]);
    }

    public function overlay(Request $request, Auction $auction)
    {
        $this->assertPublicToken($request, $auction);

        return view('auction.overlay', [
            'auction' => $auction,
            'room' => $auction->room_id,
            'state' => $this->auctions->overlayState($auction),
        ]);
    }

    public function owners(Request $request, Auction $auction)
    {
        $this->assertPublicToken($request, $auction);
        $auction->load(['teams', 'tournament']);

        return view('auction.owners', [
            'auction' => $auction,
            'room' => $auction->room_id,
            'state' => $this->auctions->overlayState($auction),
        ]);
    }

    private function assertPublicToken(Request $request, Auction $auction): void
    {
        $token = (string) $request->query('token', '');
        if ($token === '' || ! hash_equals((string) $auction->public_token, $token)) {
            abort(403);
        }
    }

    private function createViewData(?Tournament $selectedTournament): array
    {
        if ($selectedTournament?->isStandaloneHost()) {
            $selectedTournament = null;
        }

        $tournaments = Tournament::query()->visible()->latest()->get();

        return [
            'tournament' => $selectedTournament,
            'selectedTournament' => $selectedTournament,
            'tournaments' => $tournaments,
            'form' => null,
            'approvedPlayers' => collect(),
            'theme' => $selectedTournament ? $selectedTournament->theme() : OverlayThemeRegistry::get('arena'),
        ];
    }

    private function storeForTournament(Request $request, Tournament $tournament, bool $linkedTournament = true)
    {
        $template = $this->forms->getOrCreateForTournament($tournament);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'registration_mode' => ['required', Rule::in(['form', 'manual', 'both'])],
            'player_ids' => ['nullable', 'array'],
            'player_ids.*' => ['integer'],
            'manual_players' => ['nullable', 'array'],
            'manual_players.*.name' => ['nullable', 'string', 'max:200'],
            'manual_players.*.role' => ['nullable', 'string', 'max:80'],
            'manual_players.*.batting_style' => ['nullable', 'string', 'max:80'],
            'manual_players.*.bowling_style' => ['nullable', 'string', 'max:80'],
            'manual_players.*.base_price' => ['nullable', 'integer', 'min:0'],
            'manual_players.*.matches' => ['nullable'],
            'manual_players.*.runs' => ['nullable'],
            'manual_players.*.highest_score' => ['nullable'],
            'manual_players.*.wickets' => ['nullable'],
            'manual_players.*.photo' => ['nullable', 'image', 'max:4096'],
            'teams' => ['required', 'array', 'min:2'],
            'teams.*.name' => ['required', 'string', 'max:120'],
            'teams.*.short_name' => ['nullable', 'string', 'max:12'],
            'teams.*.owner' => ['nullable', 'string', 'max:120'],
            'teams.*.city' => ['nullable', 'string', 'max:120'],
            'teams.*.logo' => ['nullable', 'image', 'max:4096'],
            'rules' => ['nullable', 'array'],
            'rules.starting_purse' => ['nullable', 'integer', 'min:0'],
            'rules.default_base_price' => ['nullable', 'integer', 'min:0'],
            'rules.bid_increment' => ['nullable', 'integer', 'min:0'],
            'rules.bid_timer_seconds' => ['nullable', 'integer', 'min:5', 'max:300'],
            'rules.bid_extension_seconds' => ['nullable', 'integer', 'min:0', 'max:60'],
            'rules.min_squad' => ['nullable', 'integer', 'min:1', 'max:40'],
            'rules.max_squad' => ['nullable', 'integer', 'min:1', 'max:50'],
            'rules.team_count' => ['nullable', 'integer', 'min:2', 'max:20'],
            'rules.league_title' => ['nullable', 'string', 'max:120'],
            'rules.sponsor_line' => ['nullable', 'string', 'max:200'],
            'rules.allow_reauction' => ['nullable'],
            'base_prices' => ['nullable', 'array'],
        ]);

        $mode = $data['registration_mode'];
        $useManual = in_array($mode, ['manual', 'both'], true);

        $auction = $this->auctions->createDraft($tournament, $template, $data['name']);
        $form = $auction->registrationForm;

        if (! empty($data['rules'])) {
            $rules = $data['rules'];
            $rules['allow_reauction'] = filter_var($rules['allow_reauction'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $auction->update(['rules' => array_merge($auction->rules ?? [], $rules)]);
        }

        $teams = $this->attachTeamLogos($data['teams'], $request->file('teams', []));
        $this->auctions->syncTeams($auction, $teams);

        $registrationIds = [];
        if ($useManual) {
            $manualRows = $data['manual_players'] ?? [];
            $hasManual = collect($manualRows)->contains(fn ($row) => trim((string) ($row['name'] ?? '')) !== '');
            if ($hasManual) {
                $registrationIds = $this->createManualRegistrations(
                    $form,
                    $manualRows,
                    $request->file('manual_players', [])
                );
            }
        }

        if ($registrationIds !== []) {
            $this->auctions->addPlayersFromRegistrations(
                $auction,
                $registrationIds,
                (int) ($data['rules']['default_base_price'] ?? 20000)
            );
        }

        return redirect()
            ->route('auctions.show', ['auction' => $auction, 'tab' => 'verify'])
            ->with('success', 'Auction created. Share this auction\'s registration form or add players, then verify before going live.');
    }

    private function attachTeamLogos(array $teams, array $files = []): array
    {
        foreach ($teams as $index => &$team) {
            unset($team['logo']);
            $logo = $files[$index]['logo'] ?? null;
            if ($logo instanceof \Illuminate\Http\UploadedFile) {
                $path = $logo->store('auction-team-logos', 'public');
                $team['logo'] = '/storage/'.$path;
            }
        }
        unset($team);

        return $teams;
    }

    private function assertRegistrationBelongs(Auction $auction, PlayerRegistration $registration): void
    {
        abort_unless(
            $auction->registration_form_id
            && $registration->registration_form_id === $auction->registration_form_id,
            404
        );
    }

    private function createManualRegistrations(RegistrationForm $form, array $rows, array $files = []): array
    {
        $ids = [];

        foreach ($rows as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $role = trim((string) ($row['role'] ?? ''));
            if ($name === '') {
                continue;
            }
            if ($role === '') {
                throw ValidationException::withMessages(['manual_players' => "Add a role for {$name}."]);
            }

            $payload = array_filter([
                'full_name' => $name,
                'playing_role' => $role,
                'batting_style' => $row['batting_style'] ?? null,
                'bowling_style' => $row['bowling_style'] ?? null,
                'matches' => $row['matches'] ?? null,
                'runs' => $row['runs'] ?? null,
                'highest_score' => $row['highest_score'] ?? null,
                'wickets' => $row['wickets'] ?? null,
                'requested_base_price' => $row['base_price'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');

            $registration = PlayerRegistration::create([
                'registration_form_id' => $form->id,
                'form_version' => $form->current_version,
                'registration_code' => $this->registrations->generateCode($form),
                'data' => $payload,
                'status' => 'approved',
                'submitted_at' => now(),
                'reviewed_at' => now(),
            ]);

            $photo = $files[$index]['photo'] ?? null;
            if ($photo instanceof \Illuminate\Http\UploadedFile) {
                $this->registrations->attachProfilePhoto($registration, $photo);
            }

            $ids[] = $registration->id;
        }

        return $ids;
    }
}
