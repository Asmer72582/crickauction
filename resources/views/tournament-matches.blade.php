<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Edit Matches — {{ $tournament->name }}</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/dashboard.css">
  <link rel="stylesheet" href="/matches.css">
  <link rel="stylesheet" href="/app-shell.css">
</head>
<body class="dash-body">
@php
  $navSection = 'tournaments';
  $pageTitle = $tournament->name;
  $pageSub = 'Add teams, create matches, and prepare broadcast for this tournament.';
  $topbarNote = 'Tournament workspace';
  $brandSubline = 'Tournament setup';
  $sidebarFoot = $theme['name'];
  $topbarMeta = [
    ($isKnockout ?? false) ? 'Knockout bracket' : 'League setup',
    'Theme: '.$theme['name'],
  ];
  $subnavLabel = 'Tournament navigation';
  $subnavItems = [
    ['label' => 'Teams Library', 'url' => route('teams.index'), 'active' => false],
    ['label' => 'Matches', 'url' => route('tournaments.matches', $tournament), 'active' => true],
    ['label' => 'Broadcast', 'url' => route('broadcast.index', $tournament), 'active' => false],
    ['label' => 'Create Auction', 'url' => route('auctions.create', ['tournament' => $tournament->id]), 'active' => false],
  ];
@endphp
@include('partials.admin-shell', compact(
  'navSection',
  'pageTitle',
  'pageSub',
  'topbarNote',
  'brandSubline',
  'sidebarFoot',
  'topbarMeta',
  'subnavLabel',
  'subnavItems'
))

      <section class="matches-toolbar">
        <div class="matches-toolbar-left">
          <h1>{{ ($isKnockout ?? false) ? 'Knockout Bracket' : 'Edit Matches' }}</h1>
          <div class="overlay-info">
            <span>Theme overlay package: <strong>{{ $theme['name'] }}</strong></span>
            <span>Width: 1920 · Height: 1080</span>
            @if (!empty($tournament->champion_name))
              <span class="champion-chip">Champion: <strong>{{ $tournament->champion_name }}</strong></span>
            @endif
          </div>
        </div>
        <div class="matches-toolbar-right">
          <a class="btn-create ghost-orange" href="/tournaments/{{ $tournament->id }}/auction">Auction Desk</a>
          @unless ($isKnockout ?? false)
            <button type="button" class="btn-create" id="btn-open-match">+ Create Match</button>
            <button type="button" class="btn-create ghost-orange" id="btn-schedule-match">+ Schedule Match</button>
          @else
            <span class="ko-auto-note">15 matches auto-generated · open READY matches to score</span>
          @endunless
        </div>
      </section>

      <section class="matches-search-row">
        <input type="search" id="match-search" placeholder="Search Teams..." />
      </section>

      @if (session('success'))
        <div class="flash-ok">{{ session('success') }}</div>
      @endif

      <section class="match-grid" id="match-grid">
        @forelse ($cards as $card)
          @php $m = $card['match']; @endphp
          <article class="match-card" data-search="{{ strtolower($card['teamA']['name'].' '.$card['teamB']['name'].' '.($m->bracket_code ?? '')) }}">
            <div class="match-card-head">
              <div>
                @if ($m->bracket_code)
                  {{ $m->bracket_code }}
                  | {{ $m->round }}
                @else
                  Match No. {{ $m->match_no }}
                  | {{ $m->round }}
                @endif
                | {{ $m->overs }} Overs
                | {{ optional($m->scheduled_at)->format('d-M-Y H:i') ?? '—' }}
              </div>
              <span class="match-status-pill {{ $m->status }}">{{ ucfirst($m->status) }}</span>
            </div>

            <div class="match-vs">
              <div class="match-team">
                <div class="match-team-logo">{{ strtoupper(substr($card['teamA']['name'], 0, 1)) }}</div>
                <div class="match-team-name">{{ $card['teamA']['name'] }}</div>
                <div class="match-team-score">{{ $card['teamA']['score'] }}/{{ $card['teamA']['wickets'] }} ({{ $card['teamA']['overs'] }})</div>
              </div>
              <div class="match-vs-badge">VS</div>
              <div class="match-team">
                <div class="match-team-logo b">{{ strtoupper(substr($card['teamB']['name'], 0, 1)) }}</div>
                <div class="match-team-name">{{ $card['teamB']['name'] }}</div>
                <div class="match-team-score">{{ $card['teamB']['score'] }}/{{ $card['teamB']['wickets'] }} ({{ $card['teamB']['overs'] }})</div>
              </div>
            </div>

            <div class="match-result {{ $card['result'] ? 'has' : '' }}">
              @if ($m->winner_name)
                Winner: {{ $m->winner_name }}
                @if ($card['result']) — {{ $card['result'] }} @endif
              @else
                {{ $card['result'] ?: ($m->status === 'waiting' ? 'Waiting for prior results' : 'Match not started') }}
              @endif
            </div>

            <div class="match-card-actions">
              @if ($card['can_score'] ?? true)
                <a class="btn-link" href="{{ $card['control_url'] }}">Score</a>
              @else
                <span class="btn-link disabled" title="Both teams must be known">Waiting</span>
              @endif
              <a class="btn-link ghost" href="/broadcast/match/{{ $m->id }}">Broadcast</a>
              <a class="btn-link ghost" href="{{ $card['overlay_url'] }}" target="_blank">Legacy Overlay</a>
              <button type="button" class="btn-copy-overlay" data-url="{{ $card['overlay_url'] }}">Copy Overlay</button>
            </div>
          </article>
        @empty
          <div class="dash-empty">
            <h3>No matches yet</h3>
            <p>Create your first match for this tournament.</p>
            <button type="button" class="btn-create" id="btn-empty-match">+ Create Match</button>
          </div>
        @endforelse
      </section>

  {{-- Create / Schedule Match Modal --}}
  <div class="modal-backdrop" id="match-modal" hidden>
    <div class="modal create-modal">
      <header class="modal-head">
        <h2 id="match-modal-title">Create Match</h2>
        <button type="button" class="modal-close" data-close-match>&times;</button>
      </header>
      <form id="match-form" class="modal-body">
        <div class="form-row-2">
          <label class="team-combo-label">
            <span>Team A</span>
            <div class="team-combo" data-team-combo="a">
              <input type="text" name="team_a_name" id="team-a-name" required autocomplete="off" placeholder="Search or type a new team…" />
              <ul class="team-combo-list" id="team-a-list" hidden></ul>
            </div>
            <em class="team-combo-hint">Pick a saved team or type a new name</em>
          </label>
          <label class="team-combo-label">
            <span>Team B</span>
            <div class="team-combo" data-team-combo="b">
              <input type="text" name="team_b_name" id="team-b-name" required autocomplete="off" placeholder="Search or type a new team…" />
              <ul class="team-combo-list" id="team-b-list" hidden></ul>
            </div>
            <em class="team-combo-hint">Pick a saved team or type a new name</em>
          </label>
        </div>
        <div class="form-row-2">
          <label>
            <span>Round / Stage</span>
            <input type="text" name="round" value="League" placeholder="League / Semi Final / Final" />
          </label>
          <label>
            <span>Overs</span>
            <input type="number" name="overs" min="1" max="50" value="20" />
          </label>
        </div>
        <div class="form-row-2">
          <label>
            <span>Match No. (optional)</span>
            <input type="number" name="match_no" min="1" placeholder="Auto" />
          </label>
          <label>
            <span>Date & Time</span>
            <input type="datetime-local" name="scheduled_at" id="scheduled-at" />
          </label>
        </div>
        <div class="modal-actions">
          <button type="submit" class="btn-create full">Create Match</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Mandatory Set Toss (shown right after create — cannot dismiss) --}}
  <div class="modal-backdrop toss-gate" id="toss-modal" hidden>
    <div class="modal create-modal toss-modal" role="dialog" aria-modal="true" aria-labelledby="toss-modal-title">
      <header class="modal-head">
        <h2 id="toss-modal-title">Set Toss</h2>
      </header>
      <div class="modal-body">
        <p class="toss-gate-lead" id="toss-match-label">Complete the toss before scoring.</p>
        <label>
          <span>Toss winner</span>
          <div class="toss-choice-row" id="toss-winner-choices">
            <button type="button" class="toss-choice" data-winner="A" id="toss-pick-a">Team A</button>
            <button type="button" class="toss-choice" data-winner="B" id="toss-pick-b">Team B</button>
          </div>
        </label>
        <label>
          <span>Decision</span>
          <div class="toss-choice-row">
            <button type="button" class="toss-choice" data-decision="bat" id="toss-pick-bat">Bat</button>
            <button type="button" class="toss-choice" data-decision="bowl" id="toss-pick-bowl">Bowl</button>
          </div>
        </label>
        <p class="toss-gate-hint" id="toss-preview">Select winner and bat/bowl.</p>
        <div class="modal-actions">
          <button type="button" class="btn-create full" id="btn-confirm-toss">Save Toss &amp; Continue</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    window.TOURNAMENT_ID = {{ $tournament->id }};
    window.SAVED_TEAMS = @json($savedTeams ?? []);
  </script>
  @include('partials.admin-shell-end')
  <script src="/matches.js"></script>
</body>
</html>
