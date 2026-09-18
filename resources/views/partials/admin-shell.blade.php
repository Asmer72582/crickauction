@php
  $navSection = $navSection ?? 'tournaments';
  $pageTitle = $pageTitle ?? 'Cricket Overlay';
  $pageSub = $pageSub ?? null;
  $topbarNote = $topbarNote ?? null;
  $topbarMeta = $topbarMeta ?? [];
  $subnavLabel = $subnavLabel ?? null;
  $subnavItems = $subnavItems ?? [];
  $primaryAction = $primaryAction ?? null;
  $secondaryAction = $secondaryAction ?? null;
  $pageClass = $pageClass ?? '';

  $mainNav = [
    ['id' => 'tournaments', 'label' => 'My Tournaments', 'url' => url('/')],
    ['id' => 'auctions', 'label' => 'Auction', 'url' => url('/auctions')],
    ['id' => 'teams', 'label' => 'Teams', 'url' => url('/teams')],
    ['id' => 'wallets', 'label' => 'Realtime', 'url' => url('/wallets')],
  ];
@endphp
<button type="button" class="app-nav-toggle" id="app-nav-toggle" aria-label="Open menu">☰</button>
<div class="app-sidebar-backdrop" id="app-sidebar-backdrop"></div>
<div class="app-shell">
  <aside class="app-sidebar" id="app-sidebar">
    <div class="app-brand">
      <div class="app-brand-mark">CO</div>
      <div>
        <strong>Cricket Overlay</strong>
        <span>{{ $brandSubline ?? 'Operator Console' }}</span>
      </div>
    </div>

    <nav class="app-main-nav" aria-label="Primary">
      @foreach ($mainNav as $item)
        <a href="{{ $item['url'] }}" @class(['is-active' => $navSection === $item['id']])>{{ $item['label'] }}</a>
      @endforeach
    </nav>

    <div class="app-sidebar-foot">
      <span>{{ auth()->user()?->name ?? 'Operator' }}</span>
      @if (!empty($sidebarFoot))
        <strong>{{ $sidebarFoot }}</strong>
      @endif
      @auth
        <a class="app-account-link" href="{{ route('account.show') }}">Account</a>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="app-logout-btn">Sign out</button>
        </form>
      @endauth
    </div>
  </aside>

  <div class="app-main">
    <header class="app-topbar">
      <div class="app-topbar-copy">
        <div class="app-topbar-kicker">{{ $topbarNote ?: 'Control Desk' }}</div>
        <h1>{{ $pageTitle }}</h1>
      </div>

      <div class="app-topbar-actions">
        @foreach ($topbarMeta as $meta)
          <span class="app-meta-pill">{{ $meta }}</span>
        @endforeach

        @if (!empty($secondaryAction))
          <a
            href="{{ $secondaryAction['url'] }}"
            class="app-head-btn app-head-btn-ghost"
            target="{{ $secondaryAction['target'] ?? '_self' }}"
          >{{ $secondaryAction['label'] }}</a>
        @endif

        @if (!empty($primaryAction))
          <a
            href="{{ $primaryAction['url'] }}"
            class="app-head-btn"
            target="{{ $primaryAction['target'] ?? '_self' }}"
          >{{ $primaryAction['label'] }}</a>
        @endif
      </div>
    </header>

    @if ($subnavItems)
      <section class="app-subnav">
        <nav class="app-subnav-links" aria-label="Section">
          @foreach ($subnavItems as $item)
            <a href="{{ $item['url'] }}" @class(['is-active' => !empty($item['active'])])>{{ $item['label'] }}</a>
          @endforeach
        </nav>
      </section>
    @endif

    <main class="app-page {{ $pageClass }}">
