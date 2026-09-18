<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registered Players — {{ $tournament->name }}</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/dashboard.css">
  <link rel="stylesheet" href="/app-shell.css">
  <link rel="stylesheet" href="/auction.css">
</head>
<body class="dash-body au-desk">
@php $pageTitle = 'Registered Players'; $pageSub = $stats['total'].' total'; @endphp
@include('auction.partials.operator-shell', compact('tournament', 'theme', 'pageTitle', 'pageSub'))

<div class="au-players-page">
  <div class="au-players-toolbar">
    <form method="get" class="au-search-form">
      <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name or ID..." />
      <select name="status" aria-label="Filter by status">
        <option value="">All statuses</option>
        @foreach (['pending','approved','rejected','changes_requested'] as $s)
          <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
        @endforeach
      </select>
      <button type="submit" class="au-btn au-btn-primary au-btn-sm">Filter</button>
    </form>
    <div class="au-stat-pills">
      <span class="au-stat-pill au-stat-pill-pending">Pending <strong>{{ $stats['pending'] }}</strong></span>
      <span class="au-stat-pill au-stat-pill-approved">Approved <strong>{{ $stats['approved'] }}</strong></span>
      <span class="au-stat-pill au-stat-pill-rejected">Rejected <strong>{{ $stats['rejected'] }}</strong></span>
    </div>
  </div>

  @if ($players->isEmpty())
    <div class="au-empty au-card">
      <div class="au-empty-icon">👤</div>
      <p>No registrations yet.<br>Open your form and share the registration link.</p>
      <a class="au-btn au-btn-primary" href="{{ route('tournaments.auction', $tournament) }}" style="margin-top:16px">Go to Auction Hub</a>
    </div>
  @else
    <div class="au-players-table-card">
      <div class="au-table-wrap au-table-wrap-full">
        <table class="au-table au-players-table">
          <thead>
            <tr>
              <th class="au-col-photo"></th>
              <th>Name</th>
              <th>Auction</th>
              <th>Reg ID</th>
              <th>Role</th>
              <th class="au-col-num">M</th>
              <th class="au-col-num">Runs</th>
              <th class="au-col-num">HS</th>
              <th class="au-col-num">Wkts</th>
              <th>Status</th>
              <th>Date</th>
              <th class="au-col-action"></th>
            </tr>
          </thead>
          <tbody>
            @foreach ($players as $p)
              <tr class="au-players-row" data-href="{{ route('tournaments.registered-players.show', [$tournament, $p]) }}">
                <td>
                  @if ($p->photoUrl())
                    <img src="{{ $p->photoUrl() }}" class="au-thumb au-thumb-round" alt="">
                  @else
                    <span class="au-thumb-ph au-thumb-round">{{ strtoupper(substr($p->displayName(), 0, 1)) }}</span>
                  @endif
                </td>
                <td>
                  <strong class="au-player-name">{{ $p->displayName() }}</strong>
                  @if ($p->statValue('city'))
                    <span class="au-player-sub">{{ $p->statValue('city') }}</span>
                  @endif
                </td>
                <td>{{ $p->form?->auction?->name ?? '—' }}</td>
                <td><code class="au-reg-code-sm">{{ $p->registration_code }}</code></td>
                <td class="au-col-role">@include('auction.partials.role-badges', ['roles' => $p->rolesList()])</td>
                <td class="au-col-num">{{ $p->statValue('matches') ?: '—' }}</td>
                <td class="au-col-num">{{ $p->statValue('runs') ?: '—' }}</td>
                <td class="au-col-num">{{ $p->statValue('highest_score') ?: '—' }}</td>
                <td class="au-col-num">{{ $p->statValue('wickets') ?: '—' }}</td>
                <td><span class="au-badge au-badge-{{ $p->status }}">{{ strtoupper(str_replace('_', ' ', $p->status)) }}</span></td>
                <td class="au-col-date">{{ optional($p->submitted_at)->format('d M Y') }}</td>
                <td class="au-col-action">
                  <a class="au-btn au-btn-secondary au-btn-sm" href="{{ route('tournaments.registered-players.show', [$tournament, $p]) }}">View</a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <div class="au-player-cards">
      @foreach ($players as $p)
        <a class="au-player-card" href="{{ route('tournaments.registered-players.show', [$tournament, $p]) }}">
          @if ($p->photoUrl())
            <img src="{{ $p->photoUrl() }}" class="au-thumb au-thumb-round" alt="">
          @else
            <span class="au-thumb-ph au-thumb-round">{{ strtoupper(substr($p->displayName(), 0, 1)) }}</span>
          @endif
          <div>
            <div class="au-card-name">{{ $p->displayName() }}</div>
            <div class="au-card-meta">{{ $p->registration_code }}</div>
            @include('auction.partials.role-badges', ['roles' => $p->rolesList()])
            <div class="au-card-stats">
              <span>M {{ $p->statValue('matches') ?: '—' }}</span>
              <span>R {{ $p->statValue('runs') ?: '—' }}</span>
              <span>W {{ $p->statValue('wickets') ?: '—' }}</span>
            </div>
          </div>
          <span class="au-badge au-badge-{{ $p->status }}">{{ strtoupper($p->status) }}</span>
        </a>
      @endforeach
    </div>

    <div class="au-pagination">{{ $players->links() }}</div>
  @endif
</div>

@include('auction.partials.operator-shell-end')
<script src="/auction-ui.js"></script>
<script>
(function () {
  document.querySelectorAll('.au-players-row[data-href]').forEach((row) => {
    row.addEventListener('click', (e) => {
      if (e.target.closest('a, button')) return;
      window.location = row.dataset.href;
    });
  });
})();
</script>
</body>
</html>
