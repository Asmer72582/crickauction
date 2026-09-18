<?php

namespace App\Services;

use App\Models\SavedTeam;

/**
 * Persistent team library — squads survive across matches.
 */
class SavedTeamService
{
    public function __construct(
        protected CricketScoringService $scoring
    ) {}

    public function list(?int $tournamentId = null): array
    {
        // Team library is global for match creation. When a tournament id is
        // passed, still include every saved team so sides are always selectable.
        $q = SavedTeam::query()->orderBy('name');

        return $q->get()->map(fn (SavedTeam $t) => $t->toLibraryArray())->all();
    }

    public function find(int $id): ?SavedTeam
    {
        return SavedTeam::find($id);
    }

    /**
     * Upsert a saved team from a live match side (teamA / teamB state).
     * Skips empty names / empty squads unless $force.
     */
    public function upsertFromMatchSide(array $team, ?int $tournamentId = null, bool $force = false): ?SavedTeam
    {
        $name = trim((string) ($team['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $players = $this->scoring->sanitizeSquad($team['squad'] ?? []);
        if (! $force && $players === []) {
            return null;
        }

        $key = SavedTeam::makeKey($name);
        if ($key === '') {
            return null;
        }

        $row = SavedTeam::query()->firstOrNew(['name_key' => $key]);
        $row->name = $name;
        $row->short_name = trim((string) ($team['shortName'] ?? $row->short_name ?? strtoupper(substr($name, 0, 3))));
        $row->primary_color = $team['primaryColor'] ?? $row->primary_color;
        $row->secondary_color = $team['secondaryColor'] ?? $row->secondary_color;
        $row->logo = $team['logo'] ?? $row->logo ?? '';
        $row->players = $players;
        if ($tournamentId) {
            $row->tournament_id = $tournamentId;
        }
        $row->save();

        return $row;
    }

    public function upsertExplicit(array $data, ?int $tournamentId = null): SavedTeam
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Team name is required');
        }

        // Prefer explicit players/squad arrays; else parse comma-separated names.
        $players = $data['players'] ?? $data['squad'] ?? null;
        if (! is_array($players)) {
            $csv = (string) ($data['players_csv'] ?? $data['playersText'] ?? $data['player_names'] ?? '');
            $players = $this->playersFromCsv($csv);
        }

        $key = SavedTeam::makeKey($name);
        $row = SavedTeam::query()->firstOrNew(['name_key' => $key]);
        $row->name = $name;
        $row->short_name = trim((string) ($data['shortName'] ?? $data['short_name'] ?? strtoupper(substr($name, 0, 3))));
        $row->primary_color = $data['primaryColor'] ?? $data['primary_color'] ?? $row->primary_color;
        $row->secondary_color = $data['secondaryColor'] ?? $data['secondary_color'] ?? $row->secondary_color;
        $row->logo = $data['logo'] ?? $row->logo ?? '';
        $row->players = $this->scoring->sanitizeSquad($players);
        if ($tournamentId) {
            $row->tournament_id = $tournamentId;
        }
        $row->save();

        return $row;
    }

    /**
     * "Asmer, Ishtiyak, Player 1" → squad player arrays.
     */
    public function playersFromCsv(string $csv): array
    {
        $parts = preg_split('/[,;\n]+/', $csv) ?: [];
        $out = [];
        $seen = [];
        foreach ($parts as $part) {
            $name = trim((string) $part);
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'id' => 'sq_'.substr(md5($name.microtime(true).count($out)), 0, 8),
                'name' => $name,
            ];
        }

        return $out;
    }

    /**
     * Apply a saved team onto match state for side A or B.
     */
    public function applyToMatchState(array $state, string $side, SavedTeam $team): array
    {
        $key = strtoupper($side) === 'B' ? 'teamB' : 'teamA';
        $players = $this->scoring->sanitizeSquad($team->players ?? []);
        // Fresh ids so match-local edits don't collide with library ids across matches.
        $players = array_map(function (array $p) {
            $p['id'] = 'sq_'.substr(md5(($p['name'] ?? '').microtime(true).mt_rand()), 0, 8);

            return $p;
        }, $players);

        $state[$key]['name'] = $team->name;
        if ($team->short_name) {
            $state[$key]['shortName'] = $team->short_name;
        }
        if ($team->primary_color) {
            $state[$key]['primaryColor'] = $team->primary_color;
        }
        if ($team->secondary_color) {
            $state[$key]['secondaryColor'] = $team->secondary_color;
        }
        if ($team->logo !== null && $team->logo !== '') {
            $state[$key]['logo'] = $team->logo;
        }
        $state[$key]['squad'] = $players;

        return $this->scoring->ensureDerived($state);
    }
}
