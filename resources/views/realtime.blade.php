<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Realtime — Cricket Overlay</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/dashboard.css">
  <link rel="stylesheet" href="/app-shell.css">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="dash-body">
@php
  $navSection = 'wallets';
  $pageTitle = 'Realtime';
  $topbarNote = 'Pusher / Broadcast';
  $brandSubline = 'Live sync';
  $sidebarFoot = ($health['ok'] ?? false) ? 'Pusher ready' : 'Pusher not configured';
  $pageClass = 'app-page-desk';
  $topbarMeta = [
    ($health['ok'] ?? false) ? 'Pusher: ready' : 'Pusher: missing keys',
    'Driver: '.($health['driver'] ?? '—'),
  ];
@endphp
@include('partials.admin-shell', compact(
  'navSection',
  'pageTitle',
  'topbarNote',
  'brandSubline',
  'sidebarFoot',
  'topbarMeta',
  'pageClass'
))

  <div class="app-desk">
    <div class="app-desk-toolbar">
      <span @class(['app-desk-stat', 'is-ok' => ($health['ok'] ?? false), 'is-warn' => !($health['ok'] ?? false)])>
        Pusher <strong>{{ ($health['ok'] ?? false) ? 'Ready' : 'Not configured' }}</strong>
      </span>
      <span class="app-desk-stat">Driver <strong>{{ $health['driver'] ?? '—' }}</strong></span>
      <span class="app-desk-stat">Cluster <strong>{{ $health['cluster'] ?? '—' }}</strong></span>
      <span class="app-desk-stat">App <strong>{{ $health['app_id'] ?: '—' }}</strong></span>
    </div>

    <div class="app-desk-split !grid-cols-1 lg:!grid-cols-[1.2fr_0.8fr]">
      <section class="app-desk-panel">
        <div class="app-desk-panel-head">
          <h3>.env credentials</h3>
          <span class="text-xs text-slate-500">Laravel Broadcast → Pusher → live desk + overlays</span>
        </div>
        <div class="app-desk-scroll">
          <table class="ui-table ui-table-dense min-w-0 !min-w-full">
            <thead class="sticky top-0 z-10">
              <tr>
                <th>Key</th>
                <th>Value</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($credentials as $key => $value)
                <tr>
                  <td><code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-800">{{ $key }}</code></td>
                  <td class="font-mono text-sm text-slate-700 break-all">{{ $value === '' || $value === null ? '—' : $value }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>

      <section class="app-desk-panel">
        <div class="app-desk-panel-head"><h3>Setup</h3></div>
        <div class="app-desk-form space-y-3 text-sm text-slate-600">
          <p>Auction live desk and overlays hydrate once, then receive bids / sold / next / broadcast switches over <strong>Pusher</strong> (Laravel Broadcast). No HTTP polling.</p>
          <div>
            <div class="ui-label">Required .env</div>
            <code class="block rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-800 whitespace-pre">BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=…
PUSHER_APP_KEY=…
PUSHER_APP_SECRET=…
PUSHER_APP_CLUSTER=mt1</code>
          </div>
          <div>
            <div class="ui-label">Channel / event</div>
            <code class="block rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-800">live.&#123;room&#125; · event: realtime</code>
          </div>
          @if (!empty($health['error']))
            <p class="text-amber-700">{{ $health['error'] }}</p>
          @endif
          <a class="ui-btn-secondary ui-btn-sm" href="{{ url('/wallets') }}">Refresh status</a>
        </div>
      </section>
    </div>
  </div>

@include('partials.admin-shell-end')
</body>
</html>
