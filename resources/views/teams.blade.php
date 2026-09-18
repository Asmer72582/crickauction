<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Teams — Cricket Overlay</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/dashboard.css">
  <link rel="stylesheet" href="/teams.css">
  <link rel="stylesheet" href="/app-shell.css">
</head>
<body class="dash-body">
@php
  $navSection = 'teams';
  $pageTitle = 'Teams';
  $pageSub = 'Keep one reusable team library for tournaments, matches, and auctions.';
  $topbarNote = 'Team library';
  $brandSubline = 'Reusable squads';
  $sidebarFoot = 'Available across the project';
  $topbarMeta = ['Saved teams: '.count($teams)];
@endphp
@include('partials.admin-shell', compact(
  'navSection',
  'pageTitle',
  'pageSub',
  'topbarNote',
  'brandSubline',
  'sidebarFoot',
  'topbarMeta'
))

      <section class="dash-toolbar teams-toolbar">
        <form class="dash-search" onsubmit="return false;">
          <input type="search" id="team-search" placeholder="Search teams or players" />
        </form>
        <button type="button" class="btn-create" id="btn-open-team">+ Add Team</button>
      </section>

      <section class="teams-grid" id="teams-grid">
        @forelse ($teams as $team)
          <article class="team-card" data-id="{{ $team['id'] }}" data-search="{{ strtolower($team['name'].' '.implode(' ', array_column($team['players'] ?? [], 'name'))) }}">
            <div class="team-card-head">
              <div>
                <h2>{{ $team['name'] }}</h2>
                <p>{{ $team['playerCount'] ?? count($team['players'] ?? []) }} players</p>
              </div>
              <div class="team-card-actions">
                <button type="button" class="btn-link ghost btn-edit-team" data-id="{{ $team['id'] }}">Edit</button>
                <button type="button" class="btn-link danger btn-delete-team" data-id="{{ $team['id'] }}">Delete</button>
              </div>
            </div>
            <ul class="team-player-list">
              @forelse (($team['players'] ?? []) as $p)
                <li>{{ $p['name'] ?? '—' }}</li>
              @empty
                <li class="empty">No players yet</li>
              @endforelse
            </ul>
          </article>
        @empty
          <div class="dash-empty" id="empty-teams">
            <h3>No teams saved yet</h3>
            <p>Add a team with comma-separated player names. It will be available to import in any match.</p>
            <button type="button" class="btn-create" id="btn-empty-team">+ Add Team</button>
          </div>
        @endforelse
      </section>

  <div class="modal-backdrop" id="team-modal" hidden>
    <div class="modal create-modal">
      <header class="modal-head">
        <h2 id="team-modal-title">Add Team</h2>
        <button type="button" class="modal-close" data-close-team>&times;</button>
      </header>
      <form id="team-form" class="modal-body">
        <input type="hidden" id="team-id" value="" />
        <label>
          <span>Team name</span>
          <input type="text" id="team-name" name="name" required placeholder="e.g. Team Valote A" maxlength="80" />
        </label>
        <label>
          <span>Players (comma separated)</span>
          <textarea id="team-players-csv" name="players_csv" rows="5" placeholder="Asmer, Ishtiyak, Player 1, Player 2, …" required></textarea>
        </label>
        <p class="field-hint">Separate names with commas. Example: <em>Rohit, Virat, Jadeja</em></p>
        <div class="modal-actions">
          <button type="submit" class="btn-create full" id="btn-save-team">Save Team</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    window.INITIAL_TEAMS = @json($teams);
  </script>
  @include('partials.admin-shell-end')
  <script src="/teams.js"></script>
</body>
</html>
