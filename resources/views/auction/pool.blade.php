<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Player Pool — {{ $auction->name }}</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/dashboard.css">
  <link rel="stylesheet" href="/app-shell.css">
  <link rel="stylesheet" href="/auction.css">
</head>
<body class="dash-body au-desk">
@php $pageTitle = 'Player Pool'; $pageSub = $auction->name.' · '.$auction->players->count().' players'; @endphp
@include('auction.partials.operator-shell', compact('tournament', 'theme', 'pageTitle', 'pageSub'))

      <div class="au-table-wrap">
        <table class="au-table">
          <thead><tr><th>Player</th><th>Reg ID</th><th>Role</th><th>Base</th><th>Order</th><th>Status</th></tr></thead>
          <tbody>
            @foreach ($auction->players as $ap)
              <tr>
                <td><strong>{{ $ap->registration?->displayName() }}</strong></td>
                <td><code>{{ $ap->registration?->registration_code }}</code></td>
                <td>{{ $ap->registration?->displayRole() }}</td>
                <td>₹{{ number_format($ap->base_price) }}</td>
                <td>{{ $ap->sort_order }}</td>
                <td><span class="au-badge au-badge-{{ $ap->status === 'sold' ? 'approved' : ($ap->status === 'unsold' ? 'rejected' : 'pending') }}">{{ strtoupper($ap->status) }}</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <a href="{{ route('tournaments.auctions.live', [$tournament, $auction]) }}" class="au-btn au-btn-primary" style="margin-top:16px">Go to Live Auction</a>

@include('auction.partials.operator-shell-end')
</body>
</html>
