<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SavedTeamService;
use Illuminate\Http\Request;

class SavedTeamController extends Controller
{
    public function __construct(
        protected SavedTeamService $teams
    ) {}

    public function index(Request $request)
    {
        $tournamentId = $request->query('tournament_id');
        $tournamentId = $tournamentId !== null && $tournamentId !== '' ? (int) $tournamentId : null;

        return response()->json([
            'teams' => $this->teams->list($tournamentId),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'shortName' => ['nullable', 'string', 'max:12'],
            'primaryColor' => ['nullable', 'string', 'max:32'],
            'secondaryColor' => ['nullable', 'string', 'max:32'],
            'logo' => ['nullable', 'string', 'max:2000'],
            'players' => ['nullable'], // array or ignored if csv provided
            'squad' => ['nullable', 'array'],
            'players_csv' => ['nullable', 'string', 'max:5000'],
            'playersText' => ['nullable', 'string', 'max:5000'],
            'player_names' => ['nullable', 'string', 'max:5000'],
            'tournament_id' => ['nullable', 'integer'],
        ]);

        // Normalize players: accept array, or leave for CSV parsing in service.
        if (isset($data['players']) && is_string($data['players'])) {
            $data['players_csv'] = $data['players'];
            unset($data['players']);
        }

        try {
            $team = $this->teams->upsertExplicit($data, $data['tournament_id'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'team' => $team->toLibraryArray(),
        ], 201);
    }

    public function update(Request $request, int $team)
    {
        $row = $this->teams->find($team);
        if (! $row) {
            return response()->json(['error' => 'Team not found'], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'shortName' => ['nullable', 'string', 'max:12'],
            'primaryColor' => ['nullable', 'string', 'max:32'],
            'secondaryColor' => ['nullable', 'string', 'max:32'],
            'logo' => ['nullable', 'string', 'max:2000'],
            'players' => ['nullable'],
            'squad' => ['nullable', 'array'],
            'players_csv' => ['nullable', 'string', 'max:5000'],
            'playersText' => ['nullable', 'string', 'max:5000'],
            'player_names' => ['nullable', 'string', 'max:5000'],
            'tournament_id' => ['nullable', 'integer'],
        ]);

        if (isset($data['players']) && is_string($data['players'])) {
            $data['players_csv'] = $data['players'];
            unset($data['players']);
        }

        // Keep existing name when only updating players.
        if (empty($data['name'])) {
            $data['name'] = $row->name;
        }

        try {
            $saved = $this->teams->upsertExplicit($data, $data['tournament_id'] ?? $row->tournament_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'team' => $saved->toLibraryArray(),
        ]);
    }

    public function show(int $team)
    {
        $row = $this->teams->find($team);
        if (! $row) {
            return response()->json(['error' => 'Team not found'], 404);
        }

        return response()->json(['team' => $row->toLibraryArray()]);
    }

    public function destroy(int $team)
    {
        $row = $this->teams->find($team);
        if (! $row) {
            return response()->json(['error' => 'Team not found'], 404);
        }
        $row->delete();

        return response()->json(['ok' => true]);
    }
}
