<?php

namespace App\Http\Controllers;

use App\Models\PlayerRegistration;
use App\Models\Tournament;
use App\Services\AuctionDemoDataService;
use App\Services\RegistrationFormService;
use Illuminate\Http\Request;

class AuctionHubController extends Controller
{
    public function __construct(
        protected RegistrationFormService $forms,
        protected AuctionDemoDataService $demo
    ) {}

    public function index(Tournament $tournament)
    {
        $form = $this->forms->getOrCreateForTournament($tournament);
        $form->load('versions');

        $auctions = $tournament->auctions()->with('registrationForm')->latest()->get();
        $formIds = $auctions->pluck('registration_form_id')->filter();

        $statsQuery = PlayerRegistration::query()->whereIn('registration_form_id', $formIds);
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'pending' => (clone $statsQuery)->where('status', 'pending')->count(),
            'approved' => (clone $statsQuery)->where('status', 'approved')->count(),
            'rejected' => (clone $statsQuery)->where('status', 'rejected')->count(),
        ];

        return view('auction.hub', [
            'tournament' => $tournament,
            'form' => $form,
            'stats' => $stats,
            'auctions' => $auctions,
            'theme' => $tournament->theme(),
        ]);
    }

    public function seedDemo(Tournament $tournament)
    {
        $result = $this->demo->seedForTournament($tournament);

        $msg = sprintf(
            'Demo data loaded: %d approved players, %d teams. Auction "%s" is ready.',
            $result['players'],
            $result['teams'],
            $result['auction']->name
        );

        if ($result['created_players'] > 0) {
            $msg .= " ({$result['created_players']} new players created)";
        }

        $target = $tournament->auctions()->whereHas('players')->latest()->first()
            ?? $result['auction'];

        return redirect()
            ->route('tournaments.auctions.live', [$tournament, $target])
            ->with('success', $msg);
    }
}
