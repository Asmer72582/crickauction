<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Broadcast — {{ $tournament->name }}</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/dashboard.css">
  <link rel="stylesheet" href="/broadcast/control.css">
  <link rel="stylesheet" href="/app-shell.css">
</head>
<body class="dash-body">
@php
  $navSection = 'tournaments';
  $pageTitle = 'Broadcast';
  $pageSub = $tournament->name.' · pick a match and push its overlay into OBS or vMix.';
  $topbarNote = 'Tournament workspace';
  $brandSubline = 'Broadcast tools';
  $sidebarFoot = 'OBS / vMix ready';
  $topbarMeta = ['Matches: '.count($rows)];
  $subnavLabel = 'Tournament navigation';
  $subnavItems = [
    ['label' => 'Teams Library', 'url' => route('teams.index'), 'active' => false],
    ['label' => 'Matches', 'url' => route('tournaments.matches', $tournament), 'active' => false],
    ['label' => 'Broadcast', 'url' => route('broadcast.index', $tournament), 'active' => true],
    ['label' => 'Create Auction', 'url' => route('auctions.create', ['tournament' => $tournament->id]), 'active' => false],
  ];
@endphp
@include('partials.admin-shell', compact(
  'navSection',
  'pageTitle',
  'pageSub',
  'topbarNote',
  'brandSubline',
  'sidebarFoot',
  'topbarMeta',
  'subnavLabel',
  'subnavItems'
))

      <section class="bc-intro">
        <h1>Broadcast</h1>
        <p>Select a match, choose a theme & panel, then copy the public overlay URL into OBS Browser Source or vMix Web Browser.</p>
      </section>

      <section class="bc-grid">
        @forelse ($rows as $row)
          @php $m = $row['match']; @endphp
          <article class="bc-card">
            <div class="bc-card-head">
              <div>
                <div class="bc-match-title">Match {{ $m->match_no }} · {{ $m->team_a_name }} vs {{ $m->team_b_name }}</div>
                <div class="bc-meta">{{ $m->round }} · {{ $m->overs }} overs · {{ ucfirst($m->status) }}</div>
              </div>
              <span class="bc-live {{ $m->status === 'live' ? 'on' : '' }}">● {{ strtoupper($m->status) }}</span>
            </div>
            <div class="bc-row"><span>Theme</span><strong>{{ $row['theme']['name'] }}</strong></div>
            <div class="bc-row"><span>Panel</span><strong>{{ $panels[$row['config']->active_panel] ?? $row['config']->active_panel }}</strong></div>
            <div class="bc-url">{{ $row['obs_url'] }}</div>
            <div class="bc-actions">
              <a class="btn-link" href="/broadcast/match/{{ $m->id }}">Open Control</a>
              <a class="btn-link ghost" href="{{ $row['preview_url'] }}" target="_blank">Preview</a>
              <button type="button" class="btn-copy" data-url="{{ $row['obs_url'] }}">Copy OBS URL</button>
              <button type="button" class="btn-copy" data-url="{{ $row['vmix_url'] }}">Copy vMix URL</button>
            </div>
          </article>
        @empty
          <div class="dash-empty">
            <h3>No matches yet</h3>
            <p>Create matches first, then configure broadcast overlays.</p>
            <a class="btn-create" href="/tournaments/{{ $tournament->id }}/matches">Go to Matches</a>
          </div>
        @endforelse
      </section>
  @include('partials.admin-shell-end')
  <script>
    document.querySelectorAll(".btn-copy").forEach((btn) => {
      btn.addEventListener("click", async () => {
        try {
          await navigator.clipboard.writeText(btn.dataset.url);
          btn.textContent = "Copied!";
          setTimeout(() => { btn.textContent = btn.dataset.url.includes("vmix") || btn.textContent ? btn.getAttribute("data-label") || "Copy URL" : "Copy"; }, 1000);
          const label = btn.textContent.includes("vMix") || btn.getAttribute("data-orig");
        } catch {
          prompt("Copy URL", btn.dataset.url);
        }
      });
      btn.setAttribute("data-orig", btn.textContent);
      btn.addEventListener("click", function once() {
        const orig = btn.getAttribute("data-orig");
        setTimeout(() => { btn.textContent = orig; }, 1200);
      });
    });
  </script>
</body>
</html>
