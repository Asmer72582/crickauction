<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Auction — Cricket Overlay</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/dashboard.css">
  <link rel="stylesheet" href="/app-shell.css">
  <link rel="stylesheet" href="/auction.css">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="dash-body">
@php
  $navSection = 'auctions';
  $pageTitle = 'Auction';
  $topbarNote = 'Auction center';
  $brandSubline = 'Auction operations';
  $sidebarFoot = 'Separate from tournaments';
  $pageClass = 'app-page-desk';
  $topbarMeta = [
    'Auctions: '.$auctions->count(),
    'Tournaments: '.$tournaments->count(),
  ];
  $primaryAction = ['label' => '+ Create', 'url' => route('auctions.create')];
@endphp
@include('partials.admin-shell', compact(
  'navSection',
  'pageTitle',
  'topbarNote',
  'brandSubline',
  'sidebarFoot',
  'topbarMeta',
  'primaryAction',
  'pageClass'
))

  <div class="app-desk">
    <div class="app-desk-toolbar">
      <span class="app-desk-stat">Total <strong>{{ $auctions->count() }}</strong></span>
      <span class="app-desk-stat is-warn">Needs verify <strong>{{ $auctions->where('pending_count', '>', 0)->count() }}</strong></span>
      <span class="app-desk-stat is-ok">Live/paused <strong>{{ $auctions->whereIn('status', ['live', 'paused'])->count() }}</strong></span>
      <a class="ui-btn-primary ui-btn-sm ml-auto" href="{{ route('auctions.create') }}">Create auction</a>
    </div>

    <div class="app-desk-panel flex-1">
      <div class="app-desk-panel-head">
        <h3>Auctions</h3>
      </div>
      <div class="app-desk-scroll">
        <table class="ui-table ui-table-dense min-w-0 !min-w-full">
          <thead class="sticky top-0 z-10">
            <tr>
              <th>Auction</th>
              <th>Tournament</th>
              <th>Phase</th>
              <th>Players</th>
              <th>Teams</th>
              <th>Pending</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($auctions as $auction)
              @php
                $phase = $auction->workflowPhase();
                $badge = match ($phase) {
                  'live' => 'ui-badge-live',
                  'ready' => 'ui-badge-ready',
                  'completed' => 'ui-badge-done',
                  default => $auction->pending_count > 0 ? 'ui-badge-verify' : 'ui-badge-draft',
                };
              @endphp
              <tr>
                <td>
                  <a href="{{ route('auctions.show', $auction) }}" class="font-semibold text-slate-900 hover:text-brand">
                    {{ $auction->name }}
                  </a>
                  <div class="text-[11px] text-slate-500">{{ strtoupper($auction->status) }}</div>
                </td>
                <td>{{ $auction->displayTournamentName() }}</td>
                <td><span class="ui-badge {{ $badge }}">{{ $auction->phaseLabel() }}</span></td>
                <td>
                  <div class="font-medium text-slate-900">{{ $auction->players_count }}</div>
                  <div class="text-[11px] text-slate-500">{{ $auction->approved_count }} approved</div>
                </td>
                <td>{{ $auction->teams_count }}</td>
                <td>
                  @if ($auction->pending_count > 0)
                    <span class="ui-badge ui-badge-pending">{{ $auction->pending_count }}</span>
                  @else
                    <span class="text-slate-400">0</span>
                  @endif
                </td>
                <td>
                  <div class="flex flex-wrap justify-end gap-1">
                    <a class="ui-btn-secondary ui-btn-sm" href="{{ route('auctions.show', ['auction' => $auction, 'tab' => 'overview']) }}">Edit</a>
                    <a class="ui-btn-secondary ui-btn-sm" href="{{ route('auctions.show', ['auction' => $auction, 'tab' => 'verify']) }}">Verify</a>
                    @if (in_array($auction->status, ['published', 'live', 'paused'], true))
                      <a class="ui-btn-primary ui-btn-sm" href="{{ route('tournaments.auctions.live', [$auction->tournament_id, $auction->id]) }}">Live</a>
                    @else
                      <a class="ui-btn-ghost ui-btn-sm" href="{{ route('auctions.show', ['auction' => $auction, 'tab' => 'start']) }}">Start</a>
                    @endif
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="!py-12 text-center">
                  <p class="font-medium text-slate-900">No auctions yet</p>
                  <a class="ui-btn-primary mt-3" href="{{ route('auctions.create') }}">Create auction</a>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

@include('partials.admin-shell-end')
</body>
</html>
