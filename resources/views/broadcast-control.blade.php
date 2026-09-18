<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Broadcast Control — Match {{ $match->match_no }}</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/dashboard.css">
  <link rel="stylesheet" href="/broadcast/control.css">
</head>
<body class="dash-body">
  <div class="dash-shell">
    <aside class="dash-sidebar">
      <div class="dash-brand">
        <div class="dash-avatar">B</div>
        <div>
          <strong>Broadcast Control</strong>
          <span>Match {{ $match->match_no }}</span>
        </div>
      </div>
      <nav class="dash-nav">
        <a href="/tournaments/{{ $tournament->id }}/broadcast">All Broadcast</a>
        <a href="/tournaments/{{ $tournament->id }}/matches">Matches</a>
        <a href="{{ $match->controlUrl() }}">Score Match</a>
        <a class="active" href="/broadcast/match/{{ $match->id }}">Overlay Control</a>
      </nav>
    </aside>

    <div class="dash-main">
      <header class="dash-top">
        <div>{{ $match->team_a_name }} vs {{ $match->team_b_name }}</div>
        <div class="dash-top-right"><span id="conn-status" class="status-pill status-connected">Ready</span></div>
      </header>

      @if (session('success'))
        <div class="flash-ok" style="margin:16px 24px 0">{{ session('success') }}</div>
      @endif

      <div class="bc-control-grid">
        <section class="bc-panel-card">
          <h2>BROADCAST CONTROL</h2>
          <div class="bc-row"><span>Match</span><strong>{{ $match->team_a_name }} vs {{ $match->team_b_name }}</strong></div>
          <div class="bc-row"><span>Theme</span><strong id="theme-name">{{ $theme['name'] }}</strong></div>
          <div class="bc-row"><span>Active Panel</span><strong id="panel-name">{{ $panels[$config->active_panel] ?? $config->active_panel }}</strong></div>

          <form id="bc-form" class="bc-form">
            <label>
              <span>Theme</span>
              <select name="theme_id" id="theme_id">
                @foreach ($themes as $t)
                  <option value="{{ $t['id'] }}" @selected($config->theme_id === $t['id'])>{{ $t['name'] }} — {{ $t['description'] }}</option>
                @endforeach
              </select>
            </label>

            <div class="bc-panel-btns" id="panel-btns">
              @foreach ($panels as $id => $label)
                <button type="button" class="bc-pbtn {{ $config->active_panel === $id ? 'active' : '' }}" data-panel="{{ $id }}">{{ $label }}</button>
              @endforeach
            </div>

            <label>
              <span>Sponsor / branding text</span>
              <input type="text" name="poweredBy" value="{{ $config->branding['poweredBy'] ?? '' }}" placeholder="Powered by YOUR BRAND" />
            </label>
            <label>
              <span>Sponsor text</span>
              <input type="text" name="sponsorText" value="{{ $config->branding['sponsorText'] ?? '' }}" placeholder="Optional sponsor line" />
            </label>

            <label class="bc-check">
              <input type="checkbox" name="enabled" value="1" @checked($config->enabled) />
              Overlay enabled
            </label>

            <label class="bc-check">
              <input type="checkbox" name="regenerate_token" value="1" />
              Regenerate public token (revokes old OBS URLs)
            </label>

            <button type="submit" class="btn-create full">Save Broadcast Settings</button>
          </form>
        </section>

        <section class="bc-panel-card">
          <h2>OVERLAY LINKS</h2>
          <p class="bc-hint">Transparent 1920×1080 — paste into OBS Browser Source or vMix Web Browser. No login required.</p>

          <div class="bc-label">OBS URL</div>
          <div class="bc-url" id="obs-url">{{ $obs_url }}</div>
          <div class="bc-actions" style="margin-bottom:14px">
            <button type="button" class="btn-copy" data-target="obs-url">Copy OBS URL</button>
            <a class="btn-link ghost" id="preview-link" href="{{ $preview_url }}" target="_blank">Open Preview</a>
          </div>

          <div class="bc-label">vMix URL</div>
          <div class="bc-url" id="vmix-url">{{ $vmix_url }}</div>
          <div class="bc-actions">
            <button type="button" class="btn-copy" data-target="vmix-url">Copy vMix URL</button>
          </div>

          <div class="bc-obs-help">
            <h3>OBS setup</h3>
            <ol>
              <li>Add <strong>Browser Source</strong></li>
              <li>Paste OBS URL</li>
              <li>Width <strong>1920</strong> · Height <strong>1080</strong></li>
              <li>Shutdown when not visible: <strong>OFF</strong></li>
            </ol>
          </div>

          <iframe id="preview-frame" class="bc-preview-frame" src="{{ $preview_url }}" title="Overlay preview"></iframe>
        </section>
      </div>
    </div>
  </div>

  <script>
    window.BC = {
      matchId: {{ $match->id }},
      csrf: document.querySelector('meta[name="csrf-token"]').content,
      panels: @json($panels),
      themes: @json($themes),
    };
  </script>
  <script src="/broadcast/control.js"></script>
</body>
</html>
