<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Results — {{ $auction->name }}</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/dashboard.css">
  <link rel="stylesheet" href="/app-shell.css">
  <link rel="stylesheet" href="/auction.css">
</head>
<body class="dash-body au-desk">
@php $pageTitle = 'Auction Results'; $pageSub = $auction->name; @endphp
@include('auction.partials.operator-shell', compact('tournament', 'theme', 'pageTitle', 'pageSub'))

      <div class="au-stat-grid" style="margin-bottom:20px">
        <div class="au-stat-tile au-card"><strong>{{ $results['summary']['sold'] }}</strong><span>Sold</span></div>
        <div class="au-stat-tile au-card"><strong>{{ $results['summary']['unsold'] }}</strong><span>Unsold</span></div>
        <div class="au-stat-tile au-card"><strong>₹{{ number_format($results['summary']['totalSpent']) }}</strong><span>Spent</span></div>
        <div class="au-stat-tile au-card"><strong>₹{{ number_format($results['summary']['highestSale']) }}</strong><span>Top sale</span></div>
      </div>

      <h3 style="margin:0 0 12px;font-size:0.9rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--au-muted)">Players</h3>
      <div class="au-table-wrap" style="margin-bottom:24px">
        <table class="au-table">
          <thead><tr><th>Player</th><th>Reg ID</th><th>Base</th><th>Sold</th><th>Team</th><th>Status</th></tr></thead>
          <tbody>
            @foreach ($results['players'] as $row)
              <tr>
                <td><strong>{{ $row['name'] }}</strong></td>
                <td><code>{{ $row['registrationCode'] }}</code></td>
                <td>₹{{ number_format($row['basePrice']) }}</td>
                <td>{{ $row['soldPrice'] ? '₹'.number_format($row['soldPrice']) : '—' }}</td>
                <td>{{ $row['team'] ?? '—' }}</td>
                <td><span class="au-badge au-badge-{{ $row['status'] === 'sold' ? 'approved' : 'pending' }}">{{ strtoupper($row['status']) }}</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <h3 style="margin:0 0 12px;font-size:0.9rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--au-muted)">Teams</h3>
      <div class="au-table-wrap">
        <table class="au-table">
          <thead><tr><th>Team</th><th>Players</th><th>Spent</th><th>Remaining</th></tr></thead>
          <tbody>
            @foreach ($results['teams'] as $row)
              <tr>
                <td><strong>{{ $row['name'] }}</strong></td>
                <td>{{ $row['players'] }}</td>
                <td>₹{{ number_format($row['spent']) }}</td>
                <td>₹{{ number_format($row['remaining']) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

@include('auction.partials.operator-shell-end')
</body>
</html>
