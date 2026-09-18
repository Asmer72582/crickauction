<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Auction Hub — {{ $tournament->name }}</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/dashboard.css">
  <link rel="stylesheet" href="/app-shell.css">
  <link rel="stylesheet" href="/auction.css">
</head>
<body class="dash-body au-desk">
@php $pageTitle = 'Auction Hub'; $pageSub = $tournament->name; @endphp
@include('auction.partials.operator-shell', compact('tournament', 'theme', 'pageTitle', 'pageSub'))

      <section class="au-hub-grid">
        <article class="au-card au-card-highlight">
          <h2 style="margin-top:10px">Player Registration</h2>
          <p class="au-muted">Each auction has its own form, public link, and player list. Copy the link from that auction’s workspace.</p>
          <div class="au-btn-group">
            <a class="au-btn au-btn-secondary au-btn-sm" href="{{ route('tournaments.registration-form.edit', $tournament) }}">Edit Form Template</a>
            <a class="au-btn au-btn-ghost au-btn-sm" href="{{ route('tournaments.registration-form.preview', $tournament) }}" target="_blank">Preview</a>
            <a class="au-btn au-btn-ghost au-btn-sm" href="{{ route('tournaments.registered-players', $tournament) }}">Players ({{ $stats['total'] }})</a>
          </div>
        </article>

        <article class="au-card">
          <h3>Registered Players</h3>
          <div class="au-stat-grid">
            <div class="au-stat-tile"><strong>{{ $stats['total'] }}</strong><span>Total</span></div>
            <div class="au-stat-tile"><strong>{{ $stats['pending'] }}</strong><span>Pending</span></div>
            <div class="au-stat-tile"><strong>{{ $stats['approved'] }}</strong><span>Approved</span></div>
            <div class="au-stat-tile"><strong>{{ $stats['rejected'] }}</strong><span>Rejected</span></div>
          </div>
          <a class="au-btn au-btn-primary au-btn-block" href="{{ route('tournaments.registered-players', $tournament) }}">Review Players</a>
        </article>

        <article class="au-card au-card-wide">
          <div class="au-card-head">
            <h3>Your Auctions</h3>
            <div class="au-btn-group">
              <form method="post" action="{{ route('tournaments.auction.demo', $tournament) }}" style="display:inline">
                @csrf
                <button type="submit" class="au-btn au-btn-secondary au-btn-sm">Load Demo Data</button>
              </form>
              <a class="au-btn au-btn-primary au-btn-sm" href="{{ route('tournaments.auctions.create', $tournament) }}">+ Create Auction</a>
            </div>
          </div>
          <p class="au-muted" style="margin:0 0 12px;font-size:0.82rem">Demo data adds 15 approved players, 6 teams, and a ready-to-run auction for testing. Those players stay on the demo auction only.</p>
          @forelse ($auctions as $auction)
            <div class="au-auction-row">
              <div>
                <strong>{{ $auction->name }}</strong>
                <div class="au-muted">{{ strtoupper($auction->status) }} · {{ $auction->players()->count() }} players in pool</div>
              </div>
              <div class="au-row-actions">
                @if ($auction->registrationUrl())
                  <button type="button" class="au-btn au-btn-ghost au-btn-sm" data-copy-reg="{{ $auction->registrationUrl() }}">Copy form link</button>
                @endif
                <a href="{{ route('auctions.show', ['auction' => $auction, 'tab' => 'verify']) }}">Verify</a>
                <a href="{{ route('tournaments.auctions.pool', [$tournament, $auction]) }}">Pool</a>
                <a href="{{ route('tournaments.auctions.live', [$tournament, $auction]) }}">Live</a>
                <a href="{{ route('tournaments.auctions.results', [$tournament, $auction]) }}">Results</a>
              </div>
            </div>
          @empty
            <div class="au-empty">
              <div class="au-empty-icon">🏏</div>
              <p>No auctions yet.<br>Create one to get a unique player list and registration form.</p>
            </div>
          @endforelse
        </article>
      </section>

@include('auction.partials.operator-shell-end')
<script src="/auction-hub.js?v=2"></script>
</body>
</html>
