<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#f6f7f9">
  <title>Owner Portal — {{ $auction->name }}</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/auction-owners.css?v=2">
</head>
<body class="own-body">
  <div class="own-shell">
  <header class="own-top">
    <div>
      <p class="own-kicker" id="own-league">{{ $auction->name }}</p>
      <h1 id="own-title">Select your team</h1>
    </div>
    <button type="button" class="own-switch" id="own-switch" hidden>Change team</button>
  </header>

  <section class="own-picker" id="own-picker">
    <p class="own-lead">One link for every owner. Pick your franchise to see purse, squad, and player types.</p>
    <div class="own-picker-grid" id="own-picker-grid"></div>
  </section>

  <main class="own-desk" id="own-desk" hidden>
    <section class="own-hero" id="own-hero"></section>

    <nav class="own-tabs" role="tablist">
      <button type="button" class="own-tab is-on" data-tab="overview">Overview</button>
      <button type="button" class="own-tab" data-tab="squad">Squad</button>
      <button type="button" class="own-tab" data-tab="details">Details</button>
    </nav>

    <section class="own-panel is-on" data-panel="overview" id="own-overview"></section>
    <section class="own-panel" data-panel="squad" id="own-squad" hidden></section>
    <section class="own-panel" data-panel="details" id="own-details" hidden></section>
  </main>
  </div>

  @include('partials.pusher-echo')
  <script>
    window.AUCTION_ROOM = @json($room);
    window.AUCTION_INITIAL_STATE = @json($state);
  </script>
  <script src="/realtime-client.js?v=4"></script>
  <script src="/auction-owners.js?v=3"></script>
</body>
</html>
