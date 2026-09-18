<?php

namespace App\Http\Controllers;

use App\Models\PlayerRegistration;
use App\Models\Tournament;
use App\Services\PlayerRegistrationService;
use Illuminate\Http\Request;

class PlayerRegistrationAdminController extends Controller
{
    public function __construct(
        protected PlayerRegistrationService $registrations
    ) {}

    public function index(Request $request, Tournament $tournament)
    {
        $query = PlayerRegistration::query()
            ->whereHas('form', fn ($q) => $q->where('tournament_id', $tournament->id))
            ->with(['files', 'form.auction'])
            ->latest('submitted_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('registration_code', 'like', "%{$search}%")
                    ->orWhere('data->full_name', 'like', "%{$search}%");
            });
        }

        $base = PlayerRegistration::query()
            ->whereHas('form', fn ($q) => $q->where('tournament_id', $tournament->id));

        $stats = [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
        ];

        return view('auction.registered-players', [
            'tournament' => $tournament,
            'players' => $query->paginate(30),
            'stats' => $stats,
            'theme' => $tournament->theme(),
        ]);
    }

    public function show(Tournament $tournament, PlayerRegistration $registration)
    {
        $this->assertBelongsToTournament($tournament, $registration);

        return view('auction.player-profile', [
            'tournament' => $tournament,
            'player' => $registration->load(['files', 'form.auction']),
            'sections' => $this->registrations->profileSections($registration),
            'theme' => $tournament->theme(),
        ]);
    }

    public function approve(Request $request, Tournament $tournament, PlayerRegistration $registration)
    {
        $this->assertBelongsToTournament($tournament, $registration);
        $this->registrations->approve($registration, $request->input('notes'));

        return back()->with('success', 'Player approved.');
    }

    public function reject(Request $request, Tournament $tournament, PlayerRegistration $registration)
    {
        $this->assertBelongsToTournament($tournament, $registration);
        $this->registrations->reject($registration, $request->input('notes'));

        return back()->with('success', 'Player rejected.');
    }

    public function requestChanges(Request $request, Tournament $tournament, PlayerRegistration $registration)
    {
        $this->assertBelongsToTournament($tournament, $registration);
        $request->validate(['notes' => ['required', 'string', 'max:1000']]);
        $this->registrations->requestChanges($registration, $request->input('notes'));

        return back()->with('success', 'Change request sent.');
    }

    private function assertBelongsToTournament(Tournament $tournament, PlayerRegistration $registration): void
    {
        abort_unless($registration->form?->tournament_id === $tournament->id, 404);
    }
}
