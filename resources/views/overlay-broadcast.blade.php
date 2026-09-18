<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Broadcast Overlay</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/broadcast/overlay.css">
</head>
<body>
  <div class="bo-viewport">
    <div class="bo-canvas" id="bo-canvas" data-theme="{{ $theme['id'] }}">
      @if (!empty($preview))
        <div class="bo-preview-note">PREVIEW</div>
      @endif
      <div class="bo-mount" id="bo-mount"></div>
    </div>
  </div>

  <script>
    window.OVERLAY_BOOT = {
      matchId: {{ $match->id }},
      token: @json($token),
      themeId: @json($theme['id']),
      panel: @json($panel),
    };
  </script>
  <script src="/broadcast/overlay-engine.js"></script>
  <script>
    const boot = window.OVERLAY_BOOT;
    const engine = new BroadcastOverlayEngine({
      root: document.getElementById("bo-mount"),
      canvas: document.getElementById("bo-canvas"),
      matchId: boot.matchId,
      token: boot.token,
      themeId: boot.themeId,
      panel: boot.panel,
    });
    engine.applyTheme(@json($theme));
    engine.start();
  </script>
</body>
</html>
