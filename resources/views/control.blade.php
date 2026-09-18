<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Cricket Overlay — Control</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/style.css">
  <link rel="stylesheet" href="/control-scoring.css">
  <link rel="stylesheet" href="/control-nd.css">
  <link rel="stylesheet" href="/overlay-knockout.css">
  <link rel="stylesheet" href="/control-knockout.css">
</head>
<body class="control-body nd-desk">
  <div class="app-shell">
    <aside class="sidebar nd-side">
      <div class="sidebar-brand">
        <h1>ND Live</h1>
        <span>Scorer + Overlay</span>
      </div>
      <ul class="sidebar-nav">
        <li><a href="/">Matches</a></li>
        <li><a href="/teams">Teams</a></li>
        <li><a href="/control/knockout?room={{ urlencode($room) }}">Knockout Stage</a></li>
        <li><a href="/" class="muted-link">Tournament Info</a></li>
        <li><a href="/" class="muted-link">Points Table</a></li>
        <li><a href="/" class="muted-link">Statistics</a></li>
        <li><a href="/" class="muted-link">Awards</a></li>
        <li class="active">Live Scoring</li>
      </ul>
      <div class="sidebar-footer">Theme: {{ $theme['name'] ?? 'Midnight' }}</div>
    </aside>

    <div class="main-wrap">
      <header class="top-bar">
        <div class="top-bar-title">Edit Matches · <span class="room-badge-inline" id="current-room">{{ $room }}</span></div>
        <div class="top-bar-meta">
          <a class="btn btn-sm btn-secondary" href="/">BACK</a>
          <button type="button" class="btn btn-sm btn-accent" id="btn-match-starter" title="Play intro overlays in sequence">Match Starter</button>
          <button type="button" class="btn btn-sm btn-success" data-anim="custom" id="btn-clear-screen">Clear Screen</button>
          <button type="button" class="btn btn-sm btn-success" data-anim="tournament_name">Tournament Name</button>
          <span id="connection-status" class="status-pill status-disconnected">Connecting…</span>
          <div class="room-connect">
            <input type="text" id="room-input" value="{{ $room }}" placeholder="match1" />
            <button type="button" class="btn btn-sm btn-secondary" id="btn-connect">Connect</button>
          </div>
          <div class="overlay-link-box">
            <span>Overlay Link · 1920×1080</span>
            <input type="text" id="overlay-url" readonly />
            <button type="button" class="btn btn-sm btn-success" id="btn-copy-url">Copy</button>
          </div>
        </div>
      </header>

      <section class="match-hero nd-hero" id="live-hero">
        <div class="nd-match-meta" id="nd-match-meta">Match No. 1 | 20 Overs</div>
        <div class="match-hero-inner">
          <div class="team-score-card nd-team-card" id="hero-team-a">
            <div class="nd-team-top">
              <div class="nd-logo" id="hero-a-logo">A</div>
              <div>
                <div class="team-label" id="hero-a-name">Team A</div>
                <label class="nd-color-row">Team Color
                  <input type="color" id="team-a-color" value="#e63946" />
                </label>
              </div>
            </div>
            <div class="team-big-score" id="hero-a-score">0/0</div>
            <div class="team-overs" id="hero-a-overs">(0.0 ov)</div>
            <div class="nd-substats">
              <span id="hero-a-crr">CRR: 0.00</span>
              <span id="hero-a-proj">Projected: 0</span>
            </div>
          </div>
          <span class="vs-badge">VS</span>
          <div class="team-score-card team-b-card nd-team-card" id="hero-team-b">
            <div class="nd-team-top">
              <div>
                <div class="team-label" id="hero-b-name">Team B</div>
                <label class="nd-color-row">Team Color
                  <input type="color" id="team-b-color" value="#1d8cf8" />
                </label>
              </div>
              <div class="nd-logo" id="hero-b-logo">B</div>
            </div>
            <div class="team-big-score" id="hero-b-score">0/0</div>
            <div class="team-overs" id="hero-b-overs">(0.0 ov)</div>
            <div class="nd-substats">
              <span id="hero-b-crr">CRR: 0.00</span>
              <span id="hero-b-rrr">Req RR: —</span>
            </div>
          </div>
        </div>
        <div class="result-bar nd-toss-bar" id="hero-result">Toss not set</div>
      </section>

      {{-- Innings break (after 1st innings — match still active) --}}
      <section class="innings-break" id="innings-break" hidden>
        <div class="ib-panel">
          <div class="ib-crumb">Innings break</div>
          <h2 class="ib-title">INNINGS BREAK</h2>
          <div class="ib-finished" id="ib-finished-line">—</div>
          <div class="ib-target" id="ib-target-line">TARGET —</div>
          <p class="ib-need" id="ib-need-line">—</p>
          <div class="ib-actions">
            <button type="button" class="btn btn-primary" id="btn-start-second-innings">Start 2nd Innings</button>
            <button type="button" class="btn btn-sm btn-success" data-anim="innings_break">Show on overlay</button>
          </div>
        </div>
      </section>

      {{-- Post-match results screen (shown when match is completed) --}}
      <section class="post-match" id="post-match" hidden>
        <div class="pm-toolbar">
          <div class="pm-toolbar-left">
            <span class="pm-crumb">Match finished</span>
            <button type="button" class="btn btn-sm btn-success" data-anim="match_summary">Match Summary</button>
            <button type="button" class="btn btn-sm btn-success" data-anim="tournament_name">Tournament Name</button>
            <button type="button" class="btn btn-sm btn-success" data-anim="best_batsman">Best Batsman</button>
          </div>
          <div class="pm-toolbar-right">
            <span class="pm-match-meta" id="pm-match-meta">Match No. —</span>
            <button type="button" class="btn btn-sm btn-secondary" id="btn-resume-scoring">Resume scoring</button>
          </div>
        </div>

        <div class="pm-scores">
          <div class="pm-team-card pm-team-a">
            <div class="pm-team-name" id="pm-a-name">Team A</div>
            <div class="pm-team-score" id="pm-a-score">0/0 (0.0 ov)</div>
          </div>
          <div class="pm-vs">VS</div>
          <div class="pm-team-card pm-team-b">
            <div class="pm-team-name" id="pm-b-name">Team B</div>
            <div class="pm-team-score" id="pm-b-score">0/0 (0.0 ov)</div>
          </div>
        </div>
        <div class="pm-result-banner" id="pm-result-banner">RESULT: —</div>

        <section class="pm-pom card">
          <h2>Player of the Match</h2>
          <div class="pm-pom-row">
            <div class="pm-pom-avatar" id="pm-pom-avatar">P</div>
            <div class="pm-pom-info">
              <input type="text" id="pm-pom-name" class="wide" placeholder="Player name" />
              <div class="pm-pom-team" id="pm-pom-team">—</div>
              <div class="pm-pom-stats" id="pm-pom-stats"></div>
            </div>
            <div class="pm-pom-actions">
              <button type="button" class="btn btn-success" id="btn-pom-overlay" data-anim="best_batsman">Show on overlay</button>
              <button type="button" class="btn btn-secondary" id="btn-pom-save">Save POM</button>
            </div>
          </div>
        </section>

        <div class="pm-scorecards">
          <div class="pm-col" id="pm-col-a"></div>
          <div class="pm-col" id="pm-col-b"></div>
        </div>
      </section>

      @include('partials.control-scoring-desk')

  {{-- Mandatory Set Toss gate — cannot dismiss until toss is saved --}}
  <div class="modal-backdrop toss-gate" id="toss-gate-modal" hidden>
    <div class="modal create-modal toss-modal" role="dialog" aria-modal="true" aria-labelledby="toss-gate-title">
      <header class="modal-head">
        <h2 id="toss-gate-title">Set Toss</h2>
      </header>
      <div class="modal-body">
        <p class="toss-gate-lead">Toss must be completed before scoring this match.</p>
        <label>
          <span>Toss winner</span>
          <div class="toss-choice-row">
            <button type="button" class="toss-choice" data-winner="A" id="toss-gate-a">Team A</button>
            <button type="button" class="toss-choice" data-winner="B" id="toss-gate-b">Team B</button>
          </div>
        </label>
        <label>
          <span>Decision</span>
          <div class="toss-choice-row">
            <button type="button" class="toss-choice" data-decision="bat" id="toss-gate-bat">Bat</button>
            <button type="button" class="toss-choice" data-decision="bowl" id="toss-gate-bowl">Bowl</button>
          </div>
        </label>
        <p class="toss-gate-hint" id="toss-gate-preview">Select winner and bat/bowl.</p>
        <div class="modal-actions">
          <button type="button" class="btn btn-danger full" id="btn-toss-gate-save" style="width:100%;padding:12px;font-weight:700">Save Toss</button>
        </div>
      </div>
    </div>
  </div>

  @include('partials.pusher-echo')
  <script>
    window.CRICKET_ROOM = @json($room);
  </script>
  <script src="/realtime-client.js?v=4"></script>
  <script src="/control-knockout.js"></script>
  <script src="/control-app.js"></script>
</body>
</html>
