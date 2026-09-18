<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Knockout Stage — Cricket Overlay</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/style.css">
  <link rel="stylesheet" href="/control-scoring.css">
  <link rel="stylesheet" href="/control-nd.css">
  <link rel="stylesheet" href="/overlay-knockout.css">
  <link rel="stylesheet" href="/control-knockout.css">
</head>
<body class="control-body nd-desk ko-admin-page">
  <div class="app-shell">
    <aside class="sidebar nd-side">
      <div class="sidebar-brand">
        <h1>ND Live</h1>
        <span>Knockout Admin</span>
      </div>
      <ul class="sidebar-nav">
        <li><a href="/">Matches</a></li>
        <li><a href="/teams">Teams</a></li>
        <li><a href="/control?room={{ urlencode($room) }}">Live Scoring</a></li>
        <li class="active">Knockout Stage</li>
      </ul>
      <div class="sidebar-footer">Theme: {{ $theme['name'] ?? 'Arena Hub' }}</div>
    </aside>

    <div class="main-wrap">
      <header class="top-bar">
        <div class="top-bar-title">Knockout Stage · <span class="room-badge-inline" id="current-room">{{ $room }}</span></div>
        <div class="top-bar-meta">
          <a class="btn btn-sm btn-secondary" href="/control?room={{ urlencode($room) }}">BACK TO SCORING</a>
          <span id="connection-status" class="status-pill status-disconnected">Connecting…</span>
          <div class="room-connect">
            <input type="text" id="room-input" value="{{ $room }}" placeholder="match1" />
            <button type="button" class="btn btn-sm btn-secondary" id="btn-connect">Connect</button>
          </div>
        </div>
      </header>

      <section class="ko-admin-hero">
        <div>
          <h1>Knockout Matches</h1>
          <p>Set every bracket slot, round date, and match time. Saved data drives the stream Knockout overlay.</p>
          @if ($tournament)
            <p class="ko-admin-tour">Tournament: <strong>{{ $tournament->name }}</strong></p>
          @endif
        </div>
        <div class="ko-admin-actions">
          <button type="button" class="btn btn-secondary" id="btn-ko-save">Save bracket</button>
          <button type="button" class="btn-ko-primary" id="btn-ko-show">Save &amp; Show Overlay</button>
        </div>
      </section>

      <section class="card ko-admin-card">
        <div class="ko-admin-toolbar">
          <div class="field-row">
            <label>Title</label>
            <input type="text" id="ko-title" class="wide" placeholder="KNOCKOUT ROUND" />
          </div>
          <div class="field-row">
            <label>Format</label>
            <select id="ko-format">
              <option value="4">4 teams · Semi-finals → Final</option>
              <option value="8" selected>8 teams · Quarter-finals → Semi-finals → Final</option>
              <option value="16">16 teams · Round of 16 → Quarter-finals → Semi-finals → Final</option>
            </select>
          </div>
        </div>
        <div id="ko-bracket-editor" class="ko-board ko-editor-board ko-format-8"></div>
      </section>
    </div>
  </div>

  @include('partials.pusher-echo')
  <script>
    window.CRICKET_ROOM = @json($room);
    window.KNOCKOUT_PAGE = true;
  </script>
  <script src="/realtime-client.js?v=4"></script>
  <script src="/control-knockout.js"></script>
</body>
</html>
