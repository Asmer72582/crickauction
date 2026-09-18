<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $auction->name }} — Auction Workspace</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/dashboard.css">
  <link rel="stylesheet" href="/app-shell.css">
  <link rel="stylesheet" href="/auction.css">
  <link rel="stylesheet" href="/auction-workspace.css">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="dash-body">
@php
  $navSection = 'auctions';
  $pageTitle = $auction->name;
  $topbarNote = 'Auction workspace';
  $brandSubline = 'Auction operations';
  $sidebarFoot = $auction->displayTournamentName();
  $pageClass = 'app-page-desk aw-page';
  $topbarMeta = [strtoupper($auction->status), $auction->phaseLabel()];
  $primaryAction = in_array($auction->status, ['published', 'live', 'paused'], true)
    ? ['label' => 'Live Desk', 'url' => route('tournaments.auctions.live', [$auction->tournament_id, $auction->id])]
    : ['label' => 'Start', 'url' => route('auctions.show', ['auction' => $auction, 'tab' => 'start'])];
  $secondaryAction = ['label' => '← Back', 'url' => route('auctions.index')];
  $steps = [
    'overview' => ['n' => '01', 'kicker' => 'Briefing', 'label' => 'Overview', 'done' => true],
    'verify' => ['n' => '02', 'kicker' => 'Players', 'label' => 'Verify', 'done' => ($stats['pending'] ?? 0) === 0 && ($stats['pool'] ?? 0) > 0],
    'teams' => ['n' => '03', 'kicker' => 'Squads', 'label' => 'Teams & Pool', 'done' => ($stats['teams'] ?? 0) >= 2 && ($stats['pool'] ?? 0) >= 1],
    'start' => ['n' => '04', 'kicker' => 'Go live', 'label' => 'Start', 'done' => in_array($auction->status, ['published', 'live', 'paused', 'completed'], true)],
  ];
@endphp
@include('partials.admin-shell', compact(
  'navSection',
  'pageTitle',
  'topbarNote',
  'brandSubline',
  'sidebarFoot',
  'topbarMeta',
  'primaryAction',
  'secondaryAction',
  'pageClass'
))

  <div class="aw">
    @if (session('success'))
      <div class="aw-flash">{{ session('success') }}</div>
    @endif

    <nav class="aw-stepper" aria-label="Auction stages">
      @foreach ($steps as $key => $step)
        <a
          href="{{ route('auctions.show', ['auction' => $auction, 'tab' => $key]) }}"
          @class(['aw-step', 'is-active' => $tab === $key, 'is-done' => $step['done']])
        >
          <span class="aw-step-num">{{ $step['done'] && $tab !== $key ? '✓' : $step['n'] }}</span>
          <span class="aw-step-copy">
            <small>{{ $step['kicker'] }}</small>
            <strong>{{ $step['label'] }}</strong>
          </span>
        </a>
      @endforeach
    </nav>

    {{-- OVERVIEW --}}
    @if ($tab === 'overview')
      <section class="aw-hero">
        <div class="aw-hero-main">
          <div class="aw-hero-kicker">Floor briefing</div>
          <h2>{{ $auction->name }}</h2>
          <div class="aw-hero-meta">
            <span class="aw-chip">{{ $auction->displayTournamentName() }}</span>
            <span class="aw-chip">{{ strtoupper($auction->status) }}</span>
            <span class="aw-chip">{{ $auction->phaseLabel() }}</span>
          </div>
          <div class="aw-hero-actions">
            <button type="button" class="aw-btn aw-btn-gold aw-btn-sm" data-open-modal="modal-auction-details">Edit details</button>
          </div>
        </div>
        <div class="aw-metrics">
          <div class="aw-metric {{ ($stats['teams'] ?? 0) >= 2 ? 'is-ok' : 'is-warn' }}">
            <span>Teams</span>
            <strong>{{ $stats['teams'] }}</strong>
          </div>
          <div class="aw-metric {{ ($stats['pool'] ?? 0) >= 1 ? 'is-ok' : 'is-warn' }}">
            <span>Pool</span>
            <strong>{{ $stats['pool'] }}</strong>
          </div>
          <div class="aw-metric {{ ($stats['pending'] ?? 0) === 0 ? 'is-ok' : 'is-warn' }}">
            <span>Pending</span>
            <strong>{{ $stats['pending'] }}</strong>
          </div>
          <div class="aw-metric">
            <span>Registrations</span>
            <strong>{{ $stats['total'] }}</strong>
          </div>
        </div>
      </section>

      <div class="aw-grid-2">
        <div class="aw-panel">
          <div class="aw-panel-head">
            <div>
              <h3>Auction rules</h3>
              <p>Purse, base price, increment, and timer for the live floor.</p>
            </div>
          </div>
          <div class="aw-rules">
            <div class="aw-rule">
              <span>Starting purse</span>
              <strong>₹{{ number_format($auction->rules['starting_purse'] ?? 5000000) }}</strong>
            </div>
            <div class="aw-rule">
              <span>Default base</span>
              <strong>₹{{ number_format($auction->rules['default_base_price'] ?? 20000) }}</strong>
            </div>
            <div class="aw-rule">
              <span>Bid increment</span>
              <strong>₹{{ number_format($auction->rules['bid_increment'] ?? 10000) }}</strong>
            </div>
            <div class="aw-rule">
              <span>Bid timer</span>
              <strong>{{ $auction->rules['bid_timer_seconds'] ?? 30 }}s</strong>
            </div>
            <div class="aw-rule">
              <span>Players per team</span>
              <strong>{{ $auction->rules['max_squad'] ?? 25 }}</strong>
            </div>
          </div>
        </div>

        <div class="aw-panel">
          <div class="aw-panel-head">
            <div>
              <h3>Context</h3>
              <p>Tournament, phase, and public registration.</p>
            </div>
          </div>
          <div class="aw-context">
            <div class="aw-context-row">
              <span>Tournament</span>
              <strong>{{ $auction->displayTournamentName() }}</strong>
            </div>
            <div class="aw-context-row">
              <span>Status</span>
              <strong>{{ strtoupper($auction->status) }}</strong>
            </div>
            <div class="aw-context-row">
              <span>Phase</span>
              <strong>{{ $auction->phaseLabel() }}</strong>
            </div>
            @if ($registrationUrl)
              <div class="aw-linkbox">
                <span>This auction’s registration form</span>
                <code>{{ $registrationUrl }}</code>
                <div class="aw-linkbox-actions">
                  <a class="aw-btn aw-btn-gold aw-btn-sm" href="{{ $registrationUrl }}" target="_blank">Open form</a>
                  <button type="button" class="aw-btn aw-btn-light aw-btn-sm" onclick="navigator.clipboard.writeText(@js($registrationUrl))">Copy link</button>
                </div>
              </div>
            @endif
          </div>
        </div>
      </div>

      <div class="aw-modal" id="modal-auction-details" hidden>
        <div class="aw-modal-card" role="dialog" aria-modal="true" aria-labelledby="auction-details-title">
          <div class="aw-modal-head">
            <h3 id="auction-details-title">Edit auction details</h3>
            <button type="button" class="aw-btn aw-btn-ghost aw-btn-sm" data-close-modal>Close</button>
          </div>
          <form method="post" action="{{ route('auctions.update', $auction) }}">
            @csrf
            @method('PATCH')
            <div class="aw-modal-body">
              <div>
                <label class="ui-label" for="auction-name">Auction name</label>
                <input class="ui-input" id="auction-name" name="name" value="{{ old('name', $auction->name) }}" required />
              </div>
              <div class="grid gap-3 sm:grid-cols-2">
                <div>
                  <label class="ui-label" for="purse">Starting purse (₹)</label>
                  <input class="ui-input" id="purse" type="number" name="rules[starting_purse]" value="{{ old('rules.starting_purse', $auction->rules['starting_purse'] ?? 5000000) }}" />
                </div>
                <div>
                  <label class="ui-label" for="base">Default base price (₹)</label>
                  <input class="ui-input" id="base" type="number" name="rules[default_base_price]" value="{{ old('rules.default_base_price', $auction->rules['default_base_price'] ?? 20000) }}" />
                </div>
                <div>
                  <label class="ui-label" for="increment">Bid increment (₹)</label>
                  <input class="ui-input" id="increment" type="number" name="rules[bid_increment]" value="{{ old('rules.bid_increment', $auction->rules['bid_increment'] ?? 10000) }}" />
                </div>
                <div>
                  <label class="ui-label" for="timer">Bid timer (seconds)</label>
                  <input class="ui-input" id="timer" type="number" name="rules[bid_timer_seconds]" min="5" max="300" value="{{ old('rules.bid_timer_seconds', $auction->rules['bid_timer_seconds'] ?? 30) }}" />
                </div>
                <div>
                  <label class="ui-label" for="min-squad">Min squad</label>
                  <input class="ui-input" id="min-squad" type="number" name="rules[min_squad]" min="1" max="40" value="{{ old('rules.min_squad', $auction->rules['min_squad'] ?? 11) }}" />
                </div>
                <div>
                  <label class="ui-label" for="max-squad">Players per team</label>
                  <input class="ui-input" id="max-squad" type="number" name="rules[max_squad]" min="1" max="50" value="{{ old('rules.max_squad', $auction->rules['max_squad'] ?? 25) }}" />
                </div>
              </div>
            </div>
            <div class="aw-modal-foot">
              <button type="button" class="aw-btn aw-btn-ghost aw-btn-sm" data-close-modal>Cancel</button>
              <button class="aw-btn aw-btn-sm" type="submit">Save</button>
            </div>
          </form>
        </div>
      </div>
    @endif

    {{-- VERIFY --}}
    @if ($tab === 'verify')
      <div class="aw-grid-2">
        <div class="aw-panel">
          <div class="aw-panel-head">
            <div>
              <h3>Registrations</h3>
              <p>{{ $stats['approved'] }} approved · {{ $stats['pending'] }} pending · {{ $stats['rejected'] }} rejected</p>
            </div>
            <form method="post" action="{{ route('auctions.pool.sync', $auction) }}">
              @csrf
              <button class="aw-btn aw-btn-ghost aw-btn-sm" type="submit">Sync approved → pool</button>
            </form>
          </div>

          <div class="aw-toolbar">
            <div class="aw-filters">
            @foreach ([
              '' => 'All',
              'pending' => 'Pending',
              'approved' => 'Approved',
              'rejected' => 'Rejected',
            ] as $value => $label)
              <a
                href="{{ route('auctions.show', ['auction' => $auction, 'tab' => 'verify', 'status' => $value ?: null]) }}"
                @class(['aw-filter', 'is-active' => request('status', '') === $value])
              >{{ $label }}</a>
            @endforeach
            </div>
            <div class="aw-toolbar-end">
              <form method="get">
                <input type="hidden" name="tab" value="verify" />
                @if (request('status'))
                  <input type="hidden" name="status" value="{{ request('status') }}" />
                @endif
                <input class="aw-search" type="search" name="q" value="{{ request('q') }}" placeholder="Search player or code" />
              </form>
              @if ($registrationUrl)
                <a class="aw-btn aw-btn-ghost aw-btn-sm" href="{{ $registrationUrl }}" target="_blank">Open form</a>
                <button type="button" class="aw-btn aw-btn-ghost aw-btn-sm" onclick="navigator.clipboard.writeText(@js($registrationUrl))">Copy link</button>
              @endif
            </div>
          </div>

          <div class="aw-scroll">
            <div class="aw-list">
              @forelse ($registrations ?? [] as $player)
                @php
                  $initials = collect(preg_split('/\s+/', trim($player->displayName()) ?: 'P'))
                    ->filter()
                    ->take(2)
                    ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                    ->implode('');
                @endphp
                <article class="aw-player">
                  @if ($player->photoUrl())
                    <img src="{{ $player->photoUrl() }}" alt="" class="aw-avatar" />
                  @else
                    <div class="aw-avatar-fallback">{{ $initials ?: 'P' }}</div>
                  @endif
                  <div class="min-w-0">
                    <h4>{{ $player->displayName() }}</h4>
                    <p>
                      {{ $player->displayRole() }}
                      · <code>{{ $player->registration_code }}</code>
                      · {{ optional($player->submitted_at)->diffForHumans() }}
                    </p>
                  </div>
                  <div class="aw-player-actions">
                    <span class="aw-badge aw-badge-{{ $player->status }}">{{ $player->status }}</span>
                    @if ($player->status !== 'approved')
                      <form method="post" action="{{ route('auctions.registrations.approve', [$auction, $player]) }}">
                        @csrf
                        <input type="hidden" name="add_to_pool" value="1" />
                        <button class="aw-btn aw-btn-ok aw-btn-sm" type="submit">Approve</button>
                      </form>
                    @endif
                    @if ($player->status !== 'rejected')
                      <form method="post" action="{{ route('auctions.registrations.reject', [$auction, $player]) }}">
                        @csrf
                        <button class="aw-btn aw-btn-danger aw-btn-sm" type="submit">Reject</button>
                      </form>
                    @endif
                  </div>
                </article>
              @empty
                <div class="aw-empty">No players yet. Open the form link or add someone on the right.</div>
              @endforelse
            </div>
          </div>

          @if ($registrations)
            <div class="aw-foot">
              {{ $registrations->links() }}
            </div>
          @endif
        </div>

        <aside class="aw-panel">
          <div class="aw-panel-head">
            <div>
              <h3>Add player</h3>
              <p>Drop a player straight into the pool.</p>
            </div>
          </div>
          <form method="post" action="{{ route('auctions.players.store', $auction) }}" enctype="multipart/form-data" class="aw-form" id="add-player-form">
            @csrf
            <div>
              <label class="ui-label" for="photo">Photo</label>
              <input class="ui-input" id="photo" type="file" name="photo" accept="image/*" />
            </div>
            <div>
              <label class="ui-label" for="name">Player name</label>
              <input class="ui-input" id="name" name="name" required />
            </div>
            <div>
              <label class="ui-label">Role (max 2)</label>
              <div class="aw-roles">
                @foreach (['Batter', 'Bowler', 'All-rounder', 'Wicketkeeper'] as $role)
                  <label class="ui-check-chip !px-2.5 !py-1.5 !text-xs">
                    <input type="checkbox" name="roles[]" value="{{ $role }}" class="accent-brand" />
                    <span>{{ $role }}</span>
                  </label>
                @endforeach
              </div>
              <input type="hidden" name="role" id="composed-role" />
            </div>
            <div>
              <label class="ui-label" for="bat">Batting</label>
              <select class="ui-select" id="bat" name="batting_style">
                <option value="">Select</option>
                <option value="Right Hand">Right Hand</option>
                <option value="Left Hand">Left Hand</option>
              </select>
            </div>
            <div>
              <label class="ui-label" for="bowl">Bowling</label>
              <select class="ui-select" id="bowl" name="bowling_style">
                <option value="">Select</option>
                <option value="Right Arm Fast">Right Arm Fast</option>
                <option value="Right Arm Medium">Right Arm Medium</option>
                <option value="Right Arm Spin">Right Arm Spin</option>
                <option value="Left Arm Fast">Left Arm Fast</option>
                <option value="Left Arm Medium">Left Arm Medium</option>
                <option value="Left Arm Spin">Left Arm Spin</option>
              </select>
            </div>
            <div>
              <label class="ui-label" for="base-price">Base price</label>
              <input class="ui-input" id="base-price" type="number" name="base_price" min="0" value="{{ $auction->rules['default_base_price'] ?? 20000 }}" />
            </div>
            <input type="hidden" name="add_to_pool" value="1" />
            <button class="aw-btn w-full" type="submit">Add player</button>
          </form>
        </aside>
      </div>
    @endif

    {{-- TEAMS & POOL --}}
    @if ($tab === 'teams')
      <div class="aw-grid-equal">
        <div class="aw-panel">
          <div class="aw-panel-head">
            <div>
              <h3>Teams</h3>
              <p>{{ $auction->teams->count() }} franchises on the floor</p>
            </div>
          </div>
          <div class="aw-scroll">
            <div class="aw-team-grid">
              @forelse ($auction->teams as $team)
                <article class="aw-team-card">
                  @if ($team->logo)
                    <img src="{{ $team->logo }}" alt="" class="aw-team-logo" width="56" height="56" />
                  @else
                    <div class="aw-team-logo-fallback">{{ strtoupper(substr($team->short_name ?: $team->name, 0, 2)) }}</div>
                  @endif
                  <div class="min-w-0">
                    <h4>{{ $team->name }}</h4>
                    <p>{{ $team->short_name }}{{ $team->owner ? ' · '.$team->owner : '' }}{{ $team->city ? ' · '.$team->city : '' }}</p>
                  </div>
                  <div>
                    <div class="aw-purse">₹{{ number_format($team->remainingPurse()) }}</div>
                    <p class="aw-muted" style="margin:4px 0 0;font-size:11px">left of ₹{{ number_format($team->starting_purse) }}</p>
                    <button type="button" class="aw-btn aw-btn-ghost aw-btn-sm mt-2" data-open-modal="modal-team-{{ $team->id }}">Edit balance</button>
                  </div>
                </article>
              @empty
                <div class="aw-empty">No teams yet.</div>
              @endforelse
            </div>
          </div>
        </div>

        <div class="aw-panel">
          <div class="aw-panel-head">
            <div>
              <h3>Player pool</h3>
              <p>{{ $auction->players->count() }} players ready for the hammer</p>
            </div>
          </div>
          <div class="aw-scroll">
            <div class="aw-pool-grid">
              @forelse ($auction->players as $ap)
                <article class="aw-pool-row">
                  <div class="aw-pool-num">{{ str_pad((string) $ap->sort_order, 2, '0', STR_PAD_LEFT) }}</div>
                  <div class="min-w-0">
                    <h4>{{ $ap->registration?->displayName() }}</h4>
                    <p>{{ $ap->registration?->displayRole() ?: 'Unlisted role' }}</p>
                  </div>
                  <div>
                    <div class="aw-purse">₹{{ number_format($ap->base_price) }}</div>
                    <span class="aw-badge aw-badge-{{ $ap->status }}">{{ $ap->status }}</span>
                  </div>
                  <button type="button" class="aw-btn aw-btn-ghost aw-btn-sm" data-open-modal="modal-pool-{{ $ap->id }}">Edit</button>
                </article>
              @empty
                <div class="aw-empty">Pool is empty. Approve registrations or add a player.</div>
              @endforelse
            </div>
          </div>
        </div>
      </div>

      @foreach ($auction->teams as $team)
        <div class="aw-modal" id="modal-team-{{ $team->id }}" hidden>
          <div class="aw-modal-card" role="dialog" aria-modal="true">
            <div class="aw-modal-head">
              <h3>Edit team</h3>
              <button type="button" class="aw-btn aw-btn-ghost aw-btn-sm" data-close-modal>Close</button>
            </div>
            <form method="post" action="{{ route('auctions.teams.update', [$auction, $team]) }}" enctype="multipart/form-data">
              @csrf
              @method('PATCH')
              <div class="aw-modal-body">
                <div>
                  <label class="ui-label" for="team-name-{{ $team->id }}">Team name</label>
                  <input class="ui-input" id="team-name-{{ $team->id }}" name="name" value="{{ $team->name }}" required />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                  <div>
                    <label class="ui-label" for="team-short-{{ $team->id }}">Short</label>
                    <input class="ui-input" id="team-short-{{ $team->id }}" name="short_name" value="{{ $team->short_name }}" maxlength="12" />
                  </div>
                  <div>
                    <label class="ui-label" for="team-owner-{{ $team->id }}">Owner name</label>
                    <input class="ui-input" id="team-owner-{{ $team->id }}" name="owner" value="{{ $team->owner }}" maxlength="120" />
                  </div>
                  <div>
                    <label class="ui-label" for="team-city-{{ $team->id }}">Location / city</label>
                    <input class="ui-input" id="team-city-{{ $team->id }}" name="city" value="{{ $team->city }}" maxlength="120" />
                  </div>
                  <div>
                    <label class="ui-label" for="team-remain-{{ $team->id }}">Balance left (₹)</label>
                    <input class="ui-input" id="team-remain-{{ $team->id }}" type="number" name="remaining" min="0" value="{{ $team->remainingPurse() }}" />
                    <p class="aw-muted" style="margin:6px 0 0;font-size:11px">Spent ₹{{ number_format($team->spent_purse) }} · starting purse ₹{{ number_format($team->starting_purse) }}</p>
                  </div>
                </div>
                <div>
                  <label class="ui-label" for="team-logo-{{ $team->id }}">Logo</label>
                  <input class="ui-input" id="team-logo-{{ $team->id }}" type="file" name="logo" accept="image/*" />
                  @if ($team->logo)
                    <div class="mt-2 flex items-center gap-2 text-xs text-slate-500">
                      <img src="{{ $team->logo }}" alt="" class="aw-team-logo" width="28" height="28" style="width:28px;height:28px;border-radius:8px" />
                      Current logo
                    </div>
                  @endif
                </div>
              </div>
              <div class="aw-modal-foot">
                <button type="button" class="aw-btn aw-btn-ghost aw-btn-sm" data-close-modal>Cancel</button>
                <button class="aw-btn aw-btn-sm" type="submit">Save</button>
              </div>
            </form>
          </div>
        </div>
      @endforeach

      @foreach ($auction->players as $ap)
        <div class="aw-modal" id="modal-pool-{{ $ap->id }}" hidden>
          <div class="aw-modal-card" role="dialog" aria-modal="true">
            <div class="aw-modal-head">
              <h3>Edit pool player</h3>
              <button type="button" class="aw-btn aw-btn-ghost aw-btn-sm" data-close-modal>Close</button>
            </div>
            <form method="post" action="{{ route('auctions.pool.update', [$auction, $ap]) }}">
              @csrf
              @method('PATCH')
              <div class="aw-modal-body">
                <div>
                  <label class="ui-label" for="pool-name-{{ $ap->id }}">Player name</label>
                  <input class="ui-input" id="pool-name-{{ $ap->id }}" name="full_name" value="{{ $ap->registration?->displayName() }}" />
                </div>
                <div>
                  <label class="ui-label" for="pool-role-{{ $ap->id }}">Role</label>
                  <input class="ui-input" id="pool-role-{{ $ap->id }}" name="playing_role" value="{{ $ap->registration?->displayRole() }}" placeholder="Batter, Bowler…" />
                </div>
                <div>
                  <label class="ui-label" for="pool-base-{{ $ap->id }}">Base (₹)</label>
                  <input class="ui-input" id="pool-base-{{ $ap->id }}" type="number" name="base_price" min="0" value="{{ $ap->base_price }}" />
                </div>
              </div>
              <div class="aw-modal-foot">
                <button type="button" class="aw-btn aw-btn-ghost aw-btn-sm" data-close-modal>Cancel</button>
                <button class="aw-btn aw-btn-sm" type="submit">Save</button>
              </div>
            </form>
          </div>
        </div>
      @endforeach
    @endif

    {{-- START --}}
    @if ($tab === 'start')
      @php
        $poolReady = ($stats['pool'] ?? 0) >= 1;
        $teamsReady = ($stats['teams'] ?? 0) >= 2;
        $pendingReady = ($stats['pending'] ?? 0) === 0;
        $canPublish = $poolReady && $teamsReady;
      @endphp
      <div class="aw-launch">
        <div class="aw-launch-card">
          <small>Stage 04 · Go live</small>
          <h2>{{ in_array($auction->status, ['published', 'live', 'paused'], true) ? 'Floor is live' : 'Ready to publish?' }}</h2>
          <p>
            @if (in_array($auction->status, ['published', 'live', 'paused'], true))
              Open the live desk to run bids, or send the overlay to broadcast.
            @else
              Check the floor once more, then publish this auction to the live desk.
            @endif
          </p>
          <div class="aw-checks">
            <div @class(['aw-check', 'is-ok' => $poolReady, 'is-warn' => !$poolReady])>
              <b>{{ $stats['pool'] }}</b>
              <span>Pool</span>
            </div>
            <div @class(['aw-check', 'is-ok' => $teamsReady, 'is-warn' => !$teamsReady])>
              <b>{{ $stats['teams'] }}</b>
              <span>Teams</span>
            </div>
            <div @class(['aw-check', 'is-ok' => $pendingReady, 'is-warn' => !$pendingReady])>
              <b>{{ $stats['pending'] }}</b>
              <span>Pending</span>
            </div>
          </div>
          <div class="aw-launch-actions">
            @if ($auction->status === 'draft')
              <form method="post" action="{{ route('auctions.publish', $auction) }}">
                @csrf
                <button class="aw-btn aw-btn-gold" type="submit" @disabled(!$canPublish)>Publish auction</button>
              </form>
            @endif
            @if (in_array($auction->status, ['published', 'live', 'paused'], true))
              <a class="aw-btn aw-btn-gold" href="{{ route('tournaments.auctions.live', [$auction->tournament_id, $auction->id]) }}">Open live desk</a>
              <a class="aw-btn aw-btn-light" href="{{ $auction->overlayUrl() }}" target="_blank">Open overlay</a>
            @endif
          </div>
          @if (in_array($auction->status, ['published', 'live', 'paused'], true))
            <div class="aw-linkbox" style="margin-top:16px">
              <span>Owner portal — one link for every franchise</span>
              <code>{{ $auction->ownerPortalUrl() }}</code>
              <div class="aw-linkbox-actions">
                <a class="aw-btn aw-btn-gold aw-btn-sm" href="{{ $auction->ownerPortalUrl() }}" target="_blank">Open portal</a>
                <button type="button" class="aw-btn aw-btn-light aw-btn-sm" onclick="navigator.clipboard.writeText(@js($auction->ownerPortalUrl()))">Copy link</button>
              </div>
            </div>
          @endif
          @if (!$canPublish)
            <p class="aw-note">Need at least 1 pooled player and 2 teams before publishing.</p>
          @endif
        </div>
      </div>
    @endif
  </div>

@include('partials.admin-shell-end')
<script>
  function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.hidden = false;
    document.body.style.overflow = 'hidden';
  }

  function closeModal(el) {
    const modal = el?.closest?.('.aw-modal') || el;
    if (!modal) return;
    modal.hidden = true;
    if (![...document.querySelectorAll('.aw-modal')].some((m) => !m.hidden)) {
      document.body.style.overflow = '';
    }
  }

  document.querySelectorAll('[data-open-modal]').forEach((btn) => {
    btn.addEventListener('click', () => openModal(btn.dataset.openModal));
  });

  document.querySelectorAll('[data-close-modal]').forEach((btn) => {
    btn.addEventListener('click', () => closeModal(btn));
  });

  document.querySelectorAll('.aw-modal').forEach((modal) => {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeModal(modal);
    });
  });

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.aw-modal:not([hidden])').forEach((m) => closeModal(m));
  });

  document.getElementById('add-player-form')?.addEventListener('submit', (e) => {
    const roles = [...document.querySelectorAll('input[name="roles[]"]:checked')].map((el) => el.value);
    if (roles.length < 1) {
      e.preventDefault();
      alert('Select at least one playing role.');
      return;
    }
    if (roles.length > 2) {
      e.preventDefault();
      alert('Select maximum 2 playing roles.');
      return;
    }
    document.getElementById('composed-role').value = roles.join(', ');
  });

  document.querySelectorAll('input[name="roles[]"]').forEach((input) => {
    input.addEventListener('change', () => {
      const checked = [...document.querySelectorAll('input[name="roles[]"]:checked')];
      if (checked.length <= 2) return;
      input.checked = false;
      alert('Select maximum 2 playing roles.');
    });
  });
</script>
</body>
</html>
