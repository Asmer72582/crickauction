<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\Overlay\BroadcastThemeRegistry;
use App\Services\Overlay\OverlayService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BroadcastController extends Controller
{
    public function __construct(
        protected OverlayService $overlay
    ) {}

    public function index(Tournament $tournament)
    {
        $tournament->load(['matches' => fn ($q) => $q->orderBy('match_no')]);
        $themes = BroadcastThemeRegistry::all();
        $panels = BroadcastThemeRegistry::panels();

        $rows = $tournament->matches->map(function (TournamentMatch $match) {
            $config = $this->overlay->getOrCreateConfig($match);

            return [
                'match' => $match,
                'config' => $config,
                'theme' => BroadcastThemeRegistry::get($config->theme_id),
                'obs_url' => $config->publicUrl(),
                'vmix_url' => $config->publicUrl(),
                'preview_url' => $config->publicUrl(['preview' => 1]),
            ];
        });

        return view('broadcast', [
            'tournament' => $tournament,
            'themes' => $themes,
            'panels' => $panels,
            'rows' => $rows,
        ]);
    }

    public function show(TournamentMatch $match)
    {
        $match->load('tournament');
        $config = $this->overlay->getOrCreateConfig($match);
        $payload = $this->overlay->payload($match, $config);

        return view('broadcast-control', [
            'tournament' => $match->tournament,
            'match' => $match,
            'config' => $config,
            'themes' => BroadcastThemeRegistry::all(),
            'panels' => BroadcastThemeRegistry::panels(),
            'theme' => BroadcastThemeRegistry::get($config->theme_id),
            'obs_url' => $config->publicUrl(),
            'vmix_url' => $config->publicUrl(),
            'preview_url' => $config->publicUrl(['preview' => 1]),
            'payload' => $payload,
        ]);
    }

    public function update(Request $request, TournamentMatch $match)
    {
        $config = $this->overlay->getOrCreateConfig($match);

        $data = $request->validate([
            'theme_id' => ['sometimes', Rule::in(BroadcastThemeRegistry::ids())],
            'active_panel' => ['sometimes', Rule::in(array_keys(BroadcastThemeRegistry::panels()))],
            'enabled' => ['sometimes', 'boolean'],
            'branding' => ['sometimes', 'array'],
            'branding.tournamentLogo' => ['nullable', 'string', 'max:500'],
            'branding.sponsorLogo' => ['nullable', 'string', 'max:500'],
            'branding.sponsorText' => ['nullable', 'string', 'max:120'],
            'branding.poweredBy' => ['nullable', 'string', 'max:120'],
            'regenerate_token' => ['sometimes', 'boolean'],
        ]);

        if (! empty($data['regenerate_token'])) {
            $config->regenerateToken();
        }

        if (isset($data['theme_id'])) {
            $config->theme_id = $data['theme_id'];
        }
        if (isset($data['active_panel'])) {
            $config->active_panel = $data['active_panel'];
        }
        if (array_key_exists('enabled', $data)) {
            $config->enabled = (bool) $data['enabled'];
        }
        if (isset($data['branding'])) {
            $config->branding = array_merge($config->branding ?? [], $data['branding']);
        }
        $config->save();

        if ($request->wantsJson()) {
            return response()->json([
                'config' => $config,
                'obs_url' => $config->publicUrl(),
                'vmix_url' => $config->publicUrl(),
            ]);
        }

        return redirect('/broadcast/match/'.$match->id)
            ->with('success', 'Broadcast settings updated.');
    }
}
