<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cricket Overlay — {{ $theme['name'] }}</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/overlay.css">
  @if (($theme['package'] ?? '01') === '02')
    <link rel="stylesheet" href="/overlay-packages/overlay-02-neon-prism.css">
    <link rel="stylesheet" href="/overlay-scoreboard-prism.css">
    <link rel="stylesheet" href="/overlay-animations-prism.css">
  @else
    <link rel="stylesheet" href="/overlay-packages/overlay-01-arena-hub.css">
    <link rel="stylesheet" href="/overlay-animations-ipl.css">
    <link rel="stylesheet" href="/overlay-out-sting.css">
  @endif
  <link rel="stylesheet" href="/overlay-themes.css">
  <link rel="stylesheet" href="/overlay-knockout.css">
</head>
<body>
  <div class="broadcast-viewport" id="broadcast-viewport">
    <div
      class="broadcast-canvas"
      id="broadcast-canvas"
      data-theme="{{ $theme['id'] }}"
      data-overlay-package="{{ $theme['package'] ?? '01' }}"
    >
      <div class="graphics-root" id="graphics-root">
        <div class="scoreboard-mount" id="scoreboard-mount"></div>
        <div class="event-layer" id="event-layer"></div>
        <div class="fullscreen-layer" id="fullscreen-layer"></div>
      </div>
    </div>
  </div>

  @include('partials.pusher-echo')
  <script>
    window.CRICKET_ROOM = @json($room);
    window.CRICKET_THEME = @json($theme);
    window.OVERLAY_THEMES = @json(array_values(\App\Services\OverlayThemeRegistry::all()));
  </script>
  <script src="/realtime-client.js?v=4"></script>
  <script src="/graphics-engine.js"></script>
  <script src="/overlay-app.js?v=2"></script>
</body>
</html>
