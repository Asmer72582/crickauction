<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>My Tournaments — Cricket Overlay</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/dashboard.css">
  <link rel="stylesheet" href="/app-shell.css">
</head>
<body class="dash-body">
@php
  $navSection = 'tournaments';
  $pageTitle = 'My Tournaments';
  $pageSub = 'Create tournaments, then set up teams, matches, broadcast, and auctions in a clear order.';
  $topbarNote = 'Dashboard';
  $brandSubline = 'Tournament Console';
  $sidebarFoot = 'Simple tournament-first flow';
  $topbarMeta = [
    'Tournaments: '.$tournaments->count(),
    'Themes: '.count($themes),
  ];
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

      <section class="dash-toolbar">
        <form class="dash-search" onsubmit="return false;">
          <input type="search" id="search-input" placeholder="Search tournament" />
          <button type="button" id="btn-search">Search</button>
        </form>
        <div class="dash-toolbar-actions">
          <a class="btn-create ghost-orange" href="{{ route('auctions.create') }}">+ Create Auction</a>
          <button type="button" class="btn-create" id="btn-open-create">+ Create Tournament</button>
        </div>
      </section>

      <section class="dash-grid" id="tournament-grid">
        @forelse ($tournaments as $t)
          @php $theme = $themes[$t->theme_id] ?? $themes['arena']; @endphp
          <article class="t-card" data-name="{{ strtolower($t->name) }}">
            <div class="t-card-top">
              <div>
                <h2>{{ $t->name }}</h2>
                <p>{{ $t->sport }} | {{ $t->type }}</p>
              </div>
              <div class="t-logo" style="--accent: {{ $theme['accent'] }}">
                {{ strtoupper(substr($t->name, 0, 1)) }}
              </div>
            </div>
            <div class="t-meta">
              <div>Starts : {{ optional($t->starts_at)->format('d-m-Y') ?? '—' }}</div>
              <div>Ends : {{ optional($t->ends_at)->format('d-m-Y') ?? '—' }}</div>
            </div>
            <div class="t-theme-chip">Theme: {{ $theme['name'] }}</div>
            <div class="t-card-foot">
              <span>Assigned To : {{ $t->assigned_to }}</span>
              <span class="t-status {{ $t->status }}">{{ ucfirst($t->status) }}</span>
            </div>
            <div class="t-actions">
              <a class="btn-link" href="/tournaments/{{ $t->id }}/matches">Manage Matches</a>
              <a class="btn-link ghost" href="/auctions/create?tournament={{ $t->id }}">Create Auction</a>
              <a class="btn-link ghost" href="/tournaments/{{ $t->id }}/matches">{{ $t->matches_count }} Matches</a>
            </div>
          </article>
        @empty
          <div class="dash-empty" id="empty-state">
            <h3>No tournaments yet</h3>
            <p>Create your first tournament and pick an overlay theme.</p>
            <button type="button" class="btn-create" id="btn-empty-create">+ Create Tournament</button>
          </div>
        @endforelse
      </section>

  {{-- Create Tournament Modal --}}
  <div class="modal-backdrop" id="create-modal" hidden>
    <div class="modal create-modal">
      <header class="modal-head">
        <h2>New Tournament Details</h2>
        <button type="button" class="modal-close" data-close>&times;</button>
      </header>
      <form id="create-form" class="modal-body">
        <label>
          <span>Sports Type</span>
          <select name="sport">
            <option>Cricket</option>
          </select>
        </label>
        <label>
          <span>Name</span>
          <input type="text" name="name" required placeholder="Enter Tournament Name" />
        </label>
        <label>
          <span>Type</span>
          <select name="type">
            <option>League Tournament</option>
            <option>Knockout Tournament</option>
            <option>Friendly Series</option>
          </select>
        </label>
        <div class="form-row-2">
          <label>
            <span>No. of Wickets</span>
            <input type="number" name="wickets" min="1" max="10" value="10" />
          </label>
          <label id="groups-field">
            <span>No. of Groups</span>
            <input type="number" name="groups" min="1" max="16" value="1" />
          </label>
        </div>
        <div class="form-row-2" id="r16-schedule-row" hidden>
          <label>
            <span>Round of 16 kickoff</span>
            <input type="datetime-local" name="r16_scheduled_at" id="r16-scheduled-at" />
          </label>
          <div class="ko-hint">
            All 8 R16 matches share this time. QF / SF / Final stay unscheduled until ready.
          </div>
        </div>
        <div id="knockout-teams" class="knockout-teams" hidden>
          <div class="ko-teams-head">
            <strong>Round of 16 teams</strong>
            <span>Enter all 16 teams. The full 15-match bracket is generated automatically.</span>
          </div>
          <div class="ko-teams-grid" id="ko-teams-grid">
            @for ($i = 1; $i <= 16; $i++)
              <label>
                <span>Team {{ $i }}</span>
                <input type="text" name="teams[]" data-team-index="{{ $i }}" maxlength="80" placeholder="Team {{ $i }}" />
              </label>
            @endfor
          </div>
        </div>
        <div class="form-row-2">
          <label>
            <span>Starts</span>
            <input type="date" name="starts_at" value="{{ now()->toDateString() }}" />
          </label>
          <label>
            <span>Ends</span>
            <input type="date" name="ends_at" value="{{ now()->addDays(7)->toDateString() }}" />
          </label>
        </div>

        <div class="theme-field">
          <span>Theme</span>
          <div class="theme-selected-row">
            <input type="hidden" name="theme_id" id="theme-id" value="arena" required />
            <div class="theme-selected-preview" id="theme-selected-preview" data-theme="arena"></div>
            <div>
              <strong id="theme-selected-name">Overlay 01 · Arena Hub</strong>
              <small id="theme-selected-charge">Charges: Free · LOCKED</small>
            </div>
            <button type="button" class="btn-select-theme" id="btn-select-theme">Select Theme</button>
          </div>
        </div>

        <div class="modal-actions">
          <button type="submit" class="btn-create full">Create Tournament</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Select Theme Modal --}}
  <div class="modal-backdrop" id="theme-modal" hidden>
    <div class="modal theme-modal">
      <header class="modal-head">
        <h2>Select Theme</h2>
        <button type="button" class="modal-close" data-close-theme>&times;</button>
      </header>
      <div class="theme-grid" id="theme-grid">
        @foreach ($themes as $theme)
          <button type="button"
            class="theme-card"
            data-theme-id="{{ $theme['id'] }}"
            data-theme-name="{{ $theme['name'] }}"
            data-theme-charge="{{ $theme['charges'] }}"
            data-theme-accent="{{ $theme['accent'] }}">
            <div class="theme-card-top">
              <span>
                @if (!empty($theme['locked']))
                  LOCKED · Free
                @else
                  Charges: {{ $theme['charges'] > 0 ? 'Rs. '.$theme['charges'].'/-' : 'Free' }}
                @endif
              </span>
              <span class="theme-id">{{ $theme['package'] ? 'PKG '.$theme['package'] : $theme['id'] }}</span>
            </div>
            <div class="theme-preview-bar theme-preview-{{ $theme['id'] }}" style="--accent: {{ $theme['accent'] }}; --panel: {{ $theme['panel'] }}; --glow: {{ $theme['glow'] ?? $theme['accent'] }}">
              <div class="tp-logo"></div>
              <div class="tp-mid">
                <div class="tp-team">DUAL</div>
                <div class="tp-score">184/4</div>
              </div>
              <div class="tp-side">
                <div>Base + Accent</div>
                <div>{{ $theme['primary'] ?? $theme['panel'] }} · {{ $theme['accent'] }}</div>
              </div>
            </div>
            <div class="theme-card-name">{{ $theme['name'] }}</div>
            <div class="theme-card-desc">{{ $theme['description'] }}</div>
          </button>
        @endforeach
      </div>
    </div>
  </div>

  <script>
    window.OVERLAY_THEMES = @json(array_values($themes));
  </script>
  @include('partials.admin-shell-end')
  <script src="/dashboard.js"></script>
</body>
</html>
