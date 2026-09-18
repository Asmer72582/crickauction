<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Live Control — {{ $auction->name }}</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/app-shell.css">
  <link rel="stylesheet" href="/auction-live-control.css?v=13">
</head>
<body class="dash-body alc-body">
@php
  $overlayBase = url('/overlay/auction/'.$auction->id.'?token='.$auction->public_token);
  $youtubeOverlay = $overlayBase;
  $ownerPortalUrl = $ownerPortalUrl ?? $auction->ownerPortalUrl();
  $rules = $auction->rules ?? [];
  $increment = (int) ($rules['bid_increment'] ?? 10000);
  $timer = (int) ($rules['bid_timer_seconds'] ?? 30);
  $customInc = $rules['custom_increments'] ?? [$increment, $increment * 2, $increment * 5, 100000, 200000, 500000];
  $navSection = 'auctions';
  $pageTitle = 'Live Auction Desk';
  $pageSub = $auction->name.' · '.$tournament->name;
  $topbarNote = 'Auction center';
  $brandSubline = 'Live control';
  $sidebarFoot = $tournament->name;
  $topbarMeta = ['Live control', 'OBS ready'];
  $secondaryAction = ['label' => 'All Auctions', 'url' => route('auctions.index')];
@endphp
@include('partials.admin-shell', compact(
  'navSection',
  'pageTitle',
  'pageSub',
  'topbarNote',
  'brandSubline',
  'sidebarFoot',
  'topbarMeta',
  'secondaryAction'
))

<div id="auction-live"
  class="alc alc-cockpit"
  data-auction-id="{{ $auction->id }}"
  data-room="{{ $auction->room_id }}"
  data-increment="{{ $increment }}"
  data-overlay-base="{{ $overlayBase }}"
  data-hub="{{ route('tournaments.auction', $tournament) }}">

  <header class="alc-top">
    <div class="alc-top-left">
      <a class="alc-back" href="{{ route('tournaments.auction', $tournament) }}">← Hub</a>
      <div class="alc-brand">
        <div class="alc-title">{{ $auction->name }}</div>
        <div class="alc-sub">{{ $tournament->name }} · Live desk</div>
      </div>
      <span class="alc-status" id="live-status-pill">—</span>
      <span class="alc-status" id="alc-ws-status" data-status="offline">WS offline</span>
    </div>

    <div class="alc-airbar">
      <div class="alc-tog-group" role="group" aria-label="Overlay screen">
        <button type="button" class="alc-tog" data-broadcast="player">Player</button>
        <button type="button" class="alc-tog" data-broadcast="split">Camera</button>
        <button type="button" class="alc-tog" data-broadcast="team" id="team-display-push">Team</button>
        <button type="button" class="alc-tog" data-broadcast="teams">All Teams</button>
      </div>
      <button type="button" class="alc-btn alc-btn-sm" id="btn-toggle-urls" aria-expanded="false">YouTube link</button>
      <a class="alc-btn alc-btn-sm alc-btn-ghost" href="{{ $youtubeOverlay }}" target="_blank">Open overlay ↗</a>
    </div>

    <div class="alc-top-actions">
      <div class="alc-seg">
        <button type="button" class="alc-tab is-active" data-panel="control">Control</button>
        <button type="button" class="alc-tab" data-panel="players">Players</button>
        <button type="button" class="alc-tab" data-panel="edit">Quick Edit</button>
        <button type="button" class="alc-tab" data-panel="settings">Customise</button>
        <button type="button" class="alc-tab" data-panel="teams">All Teams</button>
      </div>
    </div>
  </header>

  <section class="alc-urls-drawer" id="urls-drawer" hidden aria-label="Share links">
    <div class="alc-owner-links">
    <article class="alc-youtube-card" data-screen="auto">
      <div class="alc-screen-top">
        <span class="alc-screen-num">▶</span>
        <div>
          <h3>YouTube / OBS overlay</h3>
          <p>One browser source. Player, Camera, Team, and All Teams all switch from this control panel.</p>
        </div>
      </div>
      <input class="alc-screen-url" readonly value="{{ $youtubeOverlay }}" data-copy-url id="youtube-overlay-url" />
      <div class="alc-screen-actions">
        <a class="alc-btn" href="{{ $youtubeOverlay }}" target="_blank">Open</a>
        <button type="button" class="alc-btn alc-btn-primary" data-copy>Copy link</button>
      </div>
    </article>
    <article class="alc-youtube-card" data-screen="owners">
      <div class="alc-screen-top">
        <span class="alc-screen-num">▣</span>
        <div>
          <h3>Owner portal</h3>
          <p>One link for every owner. They pick their team on any phone and see purse, squad, and roles.</p>
        </div>
      </div>
      <input class="alc-screen-url" readonly value="{{ $ownerPortalUrl }}" data-copy-url id="owner-portal-url" />
      <div class="alc-screen-actions">
        <a class="alc-btn" href="{{ $ownerPortalUrl }}" target="_blank">Open</a>
        <button type="button" class="alc-btn alc-btn-primary" data-copy>Copy link</button>
      </div>
    </article>
    </div>
  </section>

  <div class="alc-body-grid">
    <section class="alc-panel alc-panel-cockpit is-active" data-panel-view="control">
      <div class="alc-cockpit-main">
        {{-- Hero: player + bid status --}}
        <div class="alc-hero">
          <article class="alc-player-hero" id="live-current-player">
            <p class="alc-muted">No player on block — press <b>N</b> or Next Player</p>
          </article>

          <div class="alc-bid-status">
            <div class="alc-bid-now" id="bid-now-card">
              <em>Current Bid</em>
              <strong id="live-current-bid">₹0</strong>
              <span class="alc-leader" id="live-highest-bidder">—</span>
            </div>
            <div class="alc-bid-next">
              <em>Next Bid</em>
              <strong id="live-min-bid">₹0</strong>
              <span class="alc-pool" id="open-players-tab" role="button" tabindex="0">Pool <b id="live-pool-count">0</b></span>
            </div>
          </div>
        </div>

        <div class="alc-lot-bar" role="toolbar" aria-label="Auction actions">
          <div class="alc-tog-group alc-tog-live" role="group" aria-label="Auction run">
            <button type="button" class="alc-tog" data-action="start" id="tog-start">Start</button>
            <button type="button" class="alc-tog" data-live-toggle="1" id="tog-live">Live</button>
            <button type="button" class="alc-tog" data-action="pause" id="tog-pause">Pause</button>
          </div>
          <button type="button" class="alc-btn alc-btn-sold" id="btn-sold" data-confirm-sold="1">SOLD</button>
          <button type="button" class="alc-btn alc-btn-ghost-danger" data-action="unsold">Unsold</button>
          <button type="button" class="alc-btn alc-btn-primary" data-action="next">Next Player →</button>
          <button type="button" class="alc-btn alc-btn-ghost" id="btn-open-players">Players</button>
          <button type="button" class="alc-btn alc-btn-ghost" id="btn-open-edit">Edit Player</button>
        </div>

        {{-- Bidding controls --}}
        <div class="alc-bid-console">
          <div class="alc-bid-console-head">
            <div class="alc-console-title">Teams — tap Bid to place next amount</div>
            <div id="selected-team-label" class="alc-selected">Select a team to bid</div>
          </div>

          <div class="alc-bid-control">
            <button type="button" class="alc-stepper" id="bid-dec" aria-label="Decrease bid">−</button>
            <div class="alc-bid-amount-wrap">
              <span class="alc-bid-amount-label">Your bid</span>
              <input type="number" id="bid-amount" value="{{ $increment }}" inputmode="numeric" />
            </div>
            <button type="button" class="alc-stepper" id="bid-inc" aria-label="Increase bid">+</button>
            <button type="button" class="alc-btn" id="bid-set-min">Reset to next</button>
            <button type="button" class="alc-btn alc-btn-primary alc-btn-place" data-action="bid" id="btn-place-bid">Place Bid</button>
            <button type="button" class="alc-btn alc-btn-undo" data-action="undo-bid" id="btn-undo-bid" title="Remove the last bid (Z)">↩ Undo Bid</button>
          </div>

          <div class="alc-increments" id="bid-increments">
            @foreach ($customInc as $inc)
              <button type="button" class="alc-inc" data-inc="{{ (int) $inc }}">+{{ number_format((int) $inc) }}</button>
            @endforeach
          </div>

          <div class="alc-team-cards" id="live-team-buttons"></div>
        </div>
      </div>
    </section>

    <section class="alc-panel" data-panel-view="edit">
      <div class="alc-label">Quick Edit — Current Player</div>
      <p class="alc-help" id="edit-empty-hint">Bring a player on block to edit their details live.</p>
      <form id="quick-edit-form" class="alc-edit-form" hidden>
        <input type="hidden" id="edit-player-id" />
        <div class="alc-edit-grid">
          <label>Full Name<input type="text" id="edit-full_name" /></label>
          <label>Playing Role<input type="text" id="edit-playing_role" placeholder="Batter, Bowler…" /></label>
          <label>Batting Style<input type="text" id="edit-batting_style" /></label>
          <label>Bowling Style<input type="text" id="edit-bowling_style" /></label>
          <label>Base Price ₹<input type="number" id="edit-base_price" min="0" step="1000" /></label>
          <label>Matches<input type="number" id="edit-matches" min="0" /></label>
          <label>Runs<input type="number" id="edit-runs" min="0" /></label>
          <label>Highest Score<input type="number" id="edit-highest_score" min="0" /></label>
          <label>Wickets<input type="number" id="edit-wickets" min="0" /></label>
        </div>
        <div class="alc-place-row">
          <span class="alc-help">Average / SR / Economy recalculate automatically.</span>
          <button type="submit" class="alc-btn alc-btn-primary">Save & Push Overlay</button>
        </div>
      </form>
    </section>

    <section class="alc-panel" data-panel-view="players">
      <div class="alc-players-head">
        <div>
          <div class="alc-label">Player sequence</div>
          <p class="alc-help">This is the order Next Player will use. Drag the handle to rearrange upcoming lots. Sold and unsold stay in the list but cannot move.</p>
        </div>
        <div class="alc-players-tools">
          <input type="search" id="player-queue-search" placeholder="Search name or role" />
          <select id="player-queue-status">
            <option value="upcoming">Upcoming</option>
            <option value="all">All players</option>
            <option value="current">On block</option>
            <option value="pool">In queue</option>
            <option value="sold">Sold</option>
            <option value="unsold">Unsold</option>
          </select>
          <select id="player-queue-role">
            <option value="">All roles</option>
            <option value="batter">Batters</option>
            <option value="bowler">Bowlers</option>
            <option value="allrounder">All-rounders</option>
            <option value="wicketkeeper">Keepers</option>
          </select>
        </div>
      </div>
      <p class="alc-help" id="player-queue-meta">0 players</p>
      <div class="alc-queue" id="player-queue-list"></div>
    </section>

    <section class="alc-panel" data-panel-view="settings">
      <div class="alc-label">Auction Customisation</div>
      <form id="settings-form" class="alc-edit-form">
        <div class="alc-edit-grid">
          <label>Bid Increment ₹<input type="number" id="set-bid_increment" value="{{ $increment }}" min="1" /></label>
          <label>Bid Timer (sec)<input type="number" id="set-bid_timer_seconds" value="{{ $timer }}" min="5" max="300" /></label>
          <label>Default Base Price ₹<input type="number" id="set-default_base_price" value="{{ (int) ($rules['default_base_price'] ?? 20000) }}" min="0" /></label>
          <label>Players per team<input type="number" id="set-max_squad" value="{{ (int) ($rules['max_squad'] ?? 25) }}" min="1" max="50" /></label>
          <label>League Title<input type="text" id="set-league_title" value="{{ $rules['league_title'] ?? '' }}" placeholder="Shown on overlay header" /></label>
          <label class="alc-span-2">Sponsor / Footer Line<input type="text" id="set-sponsor_line" value="{{ $rules['sponsor_line'] ?? '' }}" placeholder="POWERED BY…" /></label>
          <label class="alc-span-2">Custom Increment Buttons (comma-separated)
            <input type="text" id="set-custom_increments" value="{{ implode(',', $customInc) }}" placeholder="10000,20000,50000,100000" />
          </label>
        </div>
        <div class="alc-place-row">
          <span class="alc-help">Changes apply live to bidding + overlay.</span>
          <button type="submit" class="alc-btn alc-btn-primary">Save Settings</button>
        </div>
      </form>
    </section>

    <section class="alc-panel" data-panel-view="teams">
      <div class="alc-teams-head">
        <div>
          <div class="alc-label">All Teams Board</div>
          <p class="alc-help">Franchises on this auction. Push a team or the full board to the YouTube overlay.</p>
        </div>
        <div class="alc-btn-row">
          <button type="button" class="alc-btn alc-btn-primary" data-broadcast="teams">Show All Teams on air</button>
        </div>
      </div>
      <div class="alc-all-teams" id="all-teams-grid"></div>
    </section>

    <aside class="alc-aside" id="alc-aside">
      <div class="alc-aside-block">
        <button type="button" class="alc-aside-toggle" id="btn-toggle-purses" aria-expanded="true">
          <span>Team Purses</span>
          <span class="alc-chevron">▾</span>
        </button>
        <div class="alc-purses" id="live-teams"></div>
      </div>

      <div class="alc-aside-block alc-activity-block">
        <div class="alc-label">Live Activity</div>
        <div class="alc-activity" id="live-activity">
          <p class="alc-muted-sm">No bids yet</p>
        </div>
      </div>

      <button type="button" class="alc-btn alc-btn-danger alc-finish" data-action="complete">Finish Auction</button>
    </aside>
  </div>

  <div class="alc-shortcuts" aria-hidden="true">⌨ Space Bid · Z Undo · S Sold · U Unsold · N Next · ↑↓ Amount</div>
  <div class="alc-toast" id="alc-toast" hidden></div>

  <div class="alc-modal" id="team-picker-modal" hidden>
    <div class="alc-modal-card alc-picker-card" role="dialog" aria-modal="true" aria-labelledby="team-picker-title">
      <div class="alc-modal-kicker">Team display</div>
      <h2 id="team-picker-title">Which team should go on air?</h2>
      <p class="alc-help">The YouTube overlay will switch to that franchise’s squad and purse.</p>
      <div class="alc-picker-grid" id="team-picker-grid"></div>
      <div class="alc-modal-actions">
        <button type="button" class="alc-btn" id="team-picker-cancel">Cancel</button>
      </div>
    </div>
  </div>

  <div class="alc-modal" id="sold-modal" hidden>
    <div class="alc-modal-card" role="dialog" aria-modal="true" aria-labelledby="sold-modal-title">
      <div class="alc-modal-kicker">Confirm sale</div>
      <h2 id="sold-modal-title">Confirm SOLD?</h2>
      <div class="alc-modal-body" id="sold-modal-body"></div>
      <div class="alc-modal-actions">
        <button type="button" class="alc-btn" id="sold-modal-cancel">Cancel</button>
        <button type="button" class="alc-btn alc-btn-sold" id="sold-modal-confirm">Confirm Sold</button>
      </div>
    </div>
  </div>
  <div class="alc-modal" id="purse-edit-modal" hidden>
    <div class="alc-modal-card" role="dialog" aria-modal="true" aria-labelledby="purse-edit-title">
      <div class="alc-modal-kicker">Team balance</div>
      <h2 id="purse-edit-title">Edit remaining purse</h2>
      <p class="alc-help" id="purse-edit-team">—</p>
      <label class="alc-edit-form" style="display:grid;gap:8px">
        Balance left (₹)
        <input type="number" id="purse-edit-remaining" min="0" />
      </label>
      <div class="alc-modal-actions">
        <button type="button" class="alc-btn" id="purse-edit-cancel">Cancel</button>
        <button type="button" class="alc-btn alc-btn-primary" id="purse-edit-save">Save balance</button>
      </div>
    </div>
  </div>
</div>

@include('partials.admin-shell-end')
@include('partials.pusher-echo')
<script>
  window.AUCTION_INITIAL_STATE = @json($state);
</script>
<script src="/realtime-client.js?v=4"></script>
<script src="/auction-live.js?v=15"></script>
</body>
</html>
