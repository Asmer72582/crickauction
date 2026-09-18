<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Auction Overlay — {{ $auction->name }}</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/vendor/animate.min.css">
  <link rel="stylesheet" href="/auction-overlay.css?v=50">
</head>
<body class="ao-body">
  <div class="ao-stage" id="ao-stage" data-view="{{ request('view', 'auto') }}" data-team-id="{{ request('teamId', '') }}">
    <div class="ao-stage-bg" aria-hidden="true"></div>

    {{-- ═══ FULL SCREEN PLAYER AUCTION — white gold card ═══ --}}
    <div class="ao-view ao-view-player" id="view-player" hidden>
      <div class="ao-fs">
        <header class="ao-fs-top">
          <div class="ao-fs-brand">
            <div class="ao-fs-shield">MCL</div>
            <span class="ao-fs-year" id="ap-year-badge">2026</span>
          </div>
          <div class="ao-fs-title-wrap">
            <svg class="ao-fs-title-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M21 16v-2l-8-5V3.5A1.5 1.5 0 0 0 11.5 2A1.5 1.5 0 0 0 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1L15 22v-1.5L13 19v-5.5z"/></svg>
            <div class="ao-fs-sub" id="ap-league-sub">PLAYER AUCTION</div>
            <span id="ap-league" hidden>MEGA CRICKET LEAGUE</span>
          </div>
          <div class="ao-fs-live">LIVE AUCTION</div>
        </header>

        <div class="ao-fs-main">
          <div class="ao-fs-spine" id="ap-spine">PLAYER</div>

          <div class="ao-fs-portrait">
            <div class="ao-wp-photo">
              <div class="ao-wp-halo" aria-hidden="true"></div>
              <div class="ao-wp-ring">
                <img id="ap-photo" src="/assets/graphics/defaultplayeravatar.png" alt="" />
              </div>
              <div class="ao-fs-id" id="ap-id">#00000</div>
            </div>
          </div>

          <div class="ao-fs-identity">
            <div class="ao-fs-nameplate">
              <div class="ao-fs-firstname" id="ap-firstname">—</div>
              <div class="ao-fs-lastname" id="ap-lastname">—</div>
            </div>
            <div class="ao-fs-rolebar">
              <span class="ao-fs-roleline" id="ap-roleline">BATTER</span>
              <span id="ap-role" hidden>BATTER</span>
              <span id="ap-style" hidden>—</span>
            </div>
            <div class="ao-fs-stats">
              <div class="ao-fs-stat">
                <span class="ao-fs-stat-ico" aria-hidden="true">
                  <svg viewBox="0 0 24 24"><path fill="currentColor" d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14a2 2 0 0 0 2 2h14c1.11 0 2-.89 2-2V6a2 2 0 0 0-2-2m0 16H5V10h14zm0-12H5V6h14z"/></svg>
                </span>
                <div class="ao-fs-stat-copy"><em>AGE</em><strong id="ap-age">—</strong></div>
              </div>
              <div class="ao-fs-stat">
                <span class="ao-fs-stat-ico" aria-hidden="true">
                  <svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2m0 18a8 8 0 1 1 8-8a8 8 0 0 1-8 8m0-14a6 6 0 1 0 6 6a6 6 0 0 0-6-6m0 10a4 4 0 1 1 4-4a4 4 0 0 1-4 4m0-6a2 2 0 1 0 2 2a2 2 0 0 0-2-2"/></svg>
                </span>
                <div class="ao-fs-stat-copy"><em>MATCHES</em><strong id="ap-matches">—</strong></div>
              </div>
              <div class="ao-fs-stat">
                <span class="ao-fs-stat-ico" aria-hidden="true">
                  <svg viewBox="0 0 24 24"><path fill="currentColor" d="M18.5 2A3.5 3.5 0 0 1 22 5.5A3.5 3.5 0 0 1 18.5 9A3.5 3.5 0 0 1 15 5.5A3.5 3.5 0 0 1 18.5 2M2.24 7.11l2.83-2.83a1 1 0 0 1 1.42 0l8.48 8.49a1 1 0 0 1 0 1.41L12.14 17a1 1 0 0 1-1.41 0L2.24 8.53a1 1 0 0 1 0-1.42M14.34 17.77l1.41-1.41L20 20.58L18.56 22z"/></svg>
                </span>
                <div class="ao-fs-stat-copy"><em>RUNS</em><strong id="ap-runs">—</strong></div>
              </div>
              <div class="ao-fs-stat">
                <span class="ao-fs-stat-ico" aria-hidden="true">
                  <svg viewBox="0 0 24 24"><path fill="currentColor" d="M16 6h2.5L12 2L5.5 6H8v3.1l4-2.3l4 2.3zm0 4.83l-4-2.32l-4 2.32V22h2v-6h4v6h2z"/></svg>
                </span>
                <div class="ao-fs-stat-copy"><em>STRIKE RATE</em><strong id="ap-sr">—</strong></div>
              </div>
            </div>
          </div>
        </div>

        <div class="ao-fs-pricebar">
          <div class="ao-fs-base" id="ap-base-wrap">
            <span>BASE PRICE</span>
            <strong id="ap-base">₹ 0</strong>
          </div>
          <div class="ao-fs-bidbox" id="ap-bidbox">
            <div class="ao-fs-bid-copy">
              <span class="ao-fs-bid-label">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M18.5 2A3.5 3.5 0 0 1 22 5.5A3.5 3.5 0 0 1 18.5 9A3.5 3.5 0 0 1 15 5.5A3.5 3.5 0 0 1 18.5 2M2.24 7.11l2.83-2.83a1 1 0 0 1 1.42 0l8.48 8.49a1 1 0 0 1 0 1.41L12.14 17a1 1 0 0 1-1.41 0L2.24 8.53a1 1 0 0 1 0-1.42M14.34 17.77l1.41-1.41L20 20.58L18.56 22z"/></svg>
                CURRENT BID
              </span>
              <div class="ao-fs-bid-value" id="ap-bid">₹ 0</div>
              <strong class="ao-fs-bidder-name" id="ap-bidder">—</strong>
            </div>
            <div class="ao-team-logo ao-team-logo-bid" id="ap-team-logo"></div>
          </div>
        </div>

        <footer class="ao-fs-foot">
          <span id="ap-footer">PLAYER AUCTION</span>
          <strong id="ap-status-text">BIDDING OPEN</strong>
        </footer>

        <aside class="ao-bid-stack" id="ap-bid-stack" hidden></aside>
      </div>
    </div>

    {{-- ═══ CAMERA OVERLAY (signature left rail + open video hole) ═══ --}}
    <div class="ao-view ao-view-split ao-view-camera" id="view-split" hidden>
      <div class="ao-cam">
        <div class="ao-cam-live"><span class="ao-live-dot"></span> LIVE AUCTION</div>

        <aside class="ao-cam-rail ao-metal-frame">
          <header class="ao-cam-rail-head">
            <div class="ao-cam-rail-mark">
              <div class="ao-fs-shield ao-cam-shield">MCL</div>
              <span id="as-year">2026</span>
            </div>
            <div class="ao-cam-rail-titles">
              <div class="ao-cam-league" id="as-league">— TAM2 —</div>
              <div class="ao-cam-sub">
                <img class="ao-cam-sub-ico" src="/assets/icons/cricket/cricket.svg" alt="" />
                PLAYER AUCTION
              </div>
            </div>
          </header>

          <div class="ao-cam-portrait">
            <div class="ao-fs-card ao-cam-card">
              <img class="ao-fs-card-frame" src="/assets/graphics/playercard_background-graphics.png" alt="" />
              <div class="ao-fs-card-player">
                <img id="as-photo" src="/assets/graphics/defaultplayeravatar.png" alt="" />
              </div>
              <div class="ao-fs-id" id="as-id">PLAYER # —</div>
            </div>
          </div>

          <div class="ao-cam-identity">
            <div class="ao-cam-role" id="as-role">BATTER</div>
            <div class="ao-cam-nameplate">
              <div class="ao-cam-firstname" id="as-firstname">—</div>
              <div class="ao-cam-lastname" id="as-lastname">—</div>
            </div>
            <div class="ao-cam-style" id="as-style">—</div>
          </div>

          <div class="ao-cam-stats">
            <div class="ao-cam-stat">
              <img class="ao-cam-stat-ico" src="/assets/icons/cricket/age.svg" alt="" />
              <em>Age</em><strong id="as-age">—</strong>
            </div>
            <div class="ao-cam-stat">
              <img class="ao-cam-stat-ico" src="/assets/icons/cricket/bat.svg" alt="" />
              <em>Runs</em><strong id="as-runs">—</strong>
            </div>
            <div class="ao-cam-stat">
              <img class="ao-cam-stat-ico" src="/assets/icons/cricket/ball.svg" alt="" />
              <em>Matches</em><strong id="as-matches">—</strong>
            </div>
            <div class="ao-cam-stat">
              <img class="ao-cam-stat-ico" src="/assets/icons/cricket/strike.svg" alt="" />
              <em>Strike Rate</em><strong id="as-sr">—</strong>
            </div>
          </div>

          <div class="ao-cam-base ao-metal-bar">
            <span>
              <img class="ao-cam-inline-ico" src="/assets/icons/cricket/trophy.svg" alt="" />
              BASE PRICE
            </span>
            <strong id="as-base">₹ 0</strong>
          </div>

          <div class="ao-cam-bidbox ao-metal-cyan" id="as-bidbox">
            <div class="ao-cam-bid-label">
              <img class="ao-cam-inline-ico ao-cam-inline-ico--cyan" src="/assets/icons/cricket/cricket.svg" alt="" />
              CURRENT BID
            </div>
            <div class="ao-cam-bid-hero">
              <div class="ao-team-logo ao-team-logo-bid" id="as-team-logo"></div>
              <div class="ao-cam-bid-meta">
                <div class="ao-cam-bid-value" id="as-bid">₹ 0</div>
                <strong class="ao-cam-bidder" id="as-bidder">—</strong>
              </div>
            </div>
          </div>

          <div class="ao-cam-status-row">
            <div class="ao-cam-status">
              <span class="ao-label">AUCTION STATUS</span>
              <strong id="as-status-text">BIDDING OPEN</strong>
            </div>
            <div class="ao-cam-timer">
              <span class="ao-label">BID TIMER</span>
              <div class="ao-fs-timer-ring">
                <svg viewBox="0 0 100 100">
                  <circle class="ao-fs-timer-bg" cx="50" cy="50" r="42"/>
                  <circle class="ao-fs-timer-fg" id="as-timer-ring" cx="50" cy="50" r="42"/>
                </svg>
                <div class="ao-fs-timer-val"><strong id="as-timer">30</strong><em>SEC</em></div>
              </div>
            </div>
          </div>
        </aside>

        <div class="ao-cam-hole" aria-hidden="true"></div>

        <footer class="ao-cam-bottom ao-metal-bottom">
          <div class="ao-cam-upnext">
            <span class="ao-cam-upnext-label">
              <img class="ao-cam-inline-ico ao-cam-inline-ico--dark" src="/assets/icons/cricket/bat.svg" alt="" />
              UP NEXT
            </span>
            <div class="ao-cam-bids" id="as-bid-history"></div>
          </div>
          <div class="ao-cam-brand-foot" id="as-footer">TAM2 PLAYER AUCTION</div>
        </footer>
      </div>
    </div>

    {{-- ═══ TEAM DISPLAY ═══ --}}
    <div class="ao-view ao-view-team" id="view-team" hidden>
      <div class="ao-team-frame">
        <header class="ao-team-brandbar">
          <div class="ao-team-logo-lg" id="at-logo"></div>
          <h2 class="ao-team-name" id="at-name">—</h2>
        </header>
        <section class="ao-team-grid-wrap">
          <div class="ao-squad-grid" id="at-grid"></div>
        </section>
      </div>
    </div>

    {{-- ═══ ALL TEAMS OVERVIEW ═══ --}}
    <div class="ao-view ao-view-teams" id="view-teams" hidden>
      <div class="ao-teams-frame">
        <header class="ao-team-topbar ao-teams-topbar">
          <div class="ao-team-top-left">
            <span class="ao-trophy">🏆</span>
            <div>
              <div class="ao-league ao-league-sm" id="ats-league">PLAYER AUCTION</div>
              <div class="ao-league-sub ao-league-sub-sm">ALL TEAMS</div>
            </div>
          </div>
          <div class="ao-team-title-badge">ALL TEAMS DISPLAY</div>
          <div class="ao-team-top-right">
            <div class="ao-live-pill"><span class="ao-live-dot"></span> AUCTION LIVE</div>
          </div>
        </header>
        <div class="ao-teams-grid" id="ats-grid"></div>
      </div>
    </div>

    {{-- Idle --}}
    <div class="ao-view ao-view-idle" id="view-idle">
      <div class="ao-idle-stage">
        <div class="ao-idle-orbit" aria-hidden="true"></div>
        <div class="ao-idle-card ao-idle-enter">
          <div class="ao-idle-league" id="ai-league">PLAYER AUCTION</div>
          <div class="ao-idle-mark">
            <span class="ao-idle-await">AWAITING</span>
            <span class="ao-idle-next">NEXT PLAYER</span>
          </div>
          <div class="ao-idle-pulse-line" aria-hidden="true"></div>
        </div>
      </div>
    </div>

    {{-- ═══ SOLD — two-sided animated card ═══ --}}
    <div class="ao-flash ao-flash-sold" id="flash-sold" hidden>
      <div class="ao-flash-veil" aria-hidden="true"></div>
      <div class="ao-fx-stage" id="sold-card">
        <div class="ao-fx-book ao-fx-sold">
          <div class="ao-fx-spine" aria-hidden="true"></div>
          <aside class="ao-fx-face ao-fx-face-l">
            <div class="ao-fx-face-glow" aria-hidden="true"></div>
            <div class="ao-fx-player">
              <img id="sold-photo" src="/assets/graphics/defaultplayeravatar.png" alt="" />
              <span class="ao-fx-rim" aria-hidden="true"></span>
            </div>
          </aside>
          <aside class="ao-fx-face ao-fx-face-r">
            <div class="ao-fx-kicker">PLAYER AUCTION</div>
            <div class="ao-fx-stamp ao-fx-stamp-sold">
              <em>S</em><em>O</em><em>L</em><em>D</em>
            </div>
            <div class="ao-fx-rule" aria-hidden="true"></div>
            <div class="ao-fx-identity">
              <span class="ao-fx-first" id="sold-firstname">—</span>
              <span class="ao-fx-last" id="sold-name">—</span>
            </div>
            <div class="ao-fx-price" id="sold-price">₹ 0</div>
            <div class="ao-fx-team">
              <div class="ao-team-logo ao-fx-team-logo" id="sold-team-logo"></div>
              <strong id="sold-team">—</strong>
            </div>
          </aside>
        </div>
      </div>
    </div>

    {{-- ═══ UNSOLD — two-sided animated card ═══ --}}
    <div class="ao-flash ao-flash-unsold" id="flash-unsold" hidden>
      <div class="ao-flash-veil" aria-hidden="true"></div>
      <div class="ao-fx-stage" id="unsold-card">
        <div class="ao-fx-book ao-fx-unsold">
          <div class="ao-fx-spine" aria-hidden="true"></div>
          <aside class="ao-fx-face ao-fx-face-l">
            <div class="ao-fx-face-glow ao-fx-face-glow-silver" aria-hidden="true"></div>
            <div class="ao-fx-player">
              <img id="unsold-photo" src="/assets/graphics/defaultplayeravatar.png" alt="" />
              <span class="ao-fx-rim ao-fx-rim-silver" aria-hidden="true"></span>
            </div>
          </aside>
          <aside class="ao-fx-face ao-fx-face-r">
            <div class="ao-fx-kicker">PLAYER AUCTION</div>
            <div class="ao-fx-stamp ao-fx-stamp-unsold">
              <em>U</em><em>N</em><em>S</em><em>O</em><em>L</em><em>D</em>
            </div>
            <div class="ao-fx-rule ao-fx-rule-silver" aria-hidden="true"></div>
            <div class="ao-fx-identity ao-fx-identity-plain">
              <span class="ao-fx-first" id="unsold-firstname">—</span>
              <span class="ao-fx-last" id="unsold-name">—</span>
            </div>
            <div class="ao-fx-sub">NO QUALIFYING BIDS</div>
            <div class="ao-fx-price" id="unsold-price" hidden>₹ 0</div>
          </aside>
        </div>
      </div>
    </div>
  </div>

  @include('partials.pusher-echo')
  <script>
    window.AUCTION_ROOM = @json($room);
    window.AUCTION_INITIAL_STATE = @json($state);
    window.AO_VIEW = @json(request('view'));
    window.AO_TEAM_ID = @json(request('teamId'));
    window.AO_SYNC = @json(request()->boolean('sync') || !request()->filled('view'));
  </script>
  <script src="/realtime-client.js?v=4"></script>
  <script src="/auction-overlay.js?v=50"></script>
</body>
</html>
