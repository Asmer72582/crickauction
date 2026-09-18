<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $player->displayName() }}</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/dashboard.css">
  <link rel="stylesheet" href="/app-shell.css">
  <link rel="stylesheet" href="/auction.css">
</head>
<body class="dash-body au-desk">
@php
  $pageTitle = $player->displayName();
  $pageSub = $player->registration_code;
@endphp
@include('auction.partials.operator-shell', compact('tournament', 'theme', 'pageTitle', 'pageSub'))

      <div class="au-profile-back">
        <a href="{{ route('tournaments.registered-players', $tournament) }}" class="au-back-link">← Registered Players</a>
      </div>

      <div class="au-profile-layout">
        <div class="au-profile-main">
          <div class="au-profile-hero-card">
            <div class="au-profile-hero-photo">
              @if ($player->photoUrl())
                <img src="{{ $player->photoUrl() }}" alt="">
              @else
                <span class="au-profile-hero-initial">{{ strtoupper(substr($player->displayName(), 0, 1)) }}</span>
              @endif
            </div>
            <div class="au-profile-hero-info">
              <div class="au-profile-hero-top">
                <span class="au-badge au-badge-{{ $player->status }}">{{ strtoupper(str_replace('_', ' ', $player->status)) }}</span>
                <code class="au-profile-code">{{ $player->registration_code }}</code>
              </div>
              <h2 class="au-profile-name">{{ $player->displayName() }}</h2>
              @include('auction.partials.role-badges', ['roles' => $player->rolesList()])
              <p class="au-profile-meta">
                Form v{{ $player->form_version }}
                · Submitted {{ optional($player->submitted_at)->format('d M Y, H:i') }}
              </p>
              @if ($player->statValue('city') || $player->statValue('mobile'))
                <p class="au-profile-contact">
                  @if ($player->statValue('city'))
                    <span>{{ $player->statValue('city') }}{{ $player->statValue('state') ? ', '.$player->statValue('state') : '' }}</span>
                  @endif
                  @if ($player->statValue('mobile'))
                    <span>{{ $player->statValue('mobile') }}</span>
                  @endif
                </p>
              @endif
            </div>
          </div>

          @if ($player->review_notes)
            <div class="au-review-notes"><strong>Review notes:</strong> {{ $player->review_notes }}</div>
          @endif

          @foreach ($sections as $section)
            <div class="au-profile-section">
              <h3>{{ $section['title'] }}</h3>
              <dl class="au-dl au-dl-grid">
                @foreach ($section['rows'] as $row)
                  <div class="au-dl-row">
                    <dt>{{ $row['label'] }}</dt>
                    <dd>
                      @if (!empty($row['file_url']))
                        <a href="{{ $row['file_url'] }}" target="_blank" rel="noopener" class="au-doc-link">
                          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 4h8l4 4v12H8z"/><path d="M16 4v4h4"/></svg>
                          {{ $row['value'] }}
                        </a>
                      @else
                        {{ $row['value'] }}
                      @endif
                    </dd>
                  </div>
                @endforeach
              </dl>
            </div>
          @endforeach
        </div>

        <aside class="au-profile-aside">
          @if ($player->hasStats())
            @include('auction.partials.player-stats-readonly', ['player' => $player])
          @endif

          <div class="au-profile-actions-card">
            <h4>Review</h4>
            <div class="au-btn-group au-btn-group-stack">
              @if ($player->status !== 'approved')
                <form method="post" action="{{ route('tournaments.registered-players.approve', [$tournament, $player]) }}">@csrf
                  <button class="au-btn au-btn-primary au-btn-block" type="submit">✓ Approve Player</button>
                </form>
              @endif
              @if ($player->status !== 'rejected')
                <form method="post" action="{{ route('tournaments.registered-players.reject', [$tournament, $player]) }}">@csrf
                  <button class="au-btn au-btn-ghost au-btn-block" type="submit">Reject</button>
                </form>
              @endif
            </div>
            <form method="post" action="{{ route('tournaments.registered-players.changes', [$tournament, $player]) }}" class="au-form-field">
              @csrf
              <span>Request changes</span>
              <textarea name="notes" rows="3" placeholder="What should the player update?" required></textarea>
              <button type="submit" class="au-btn au-btn-secondary au-btn-sm au-btn-block" style="margin-top:8px">Send request</button>
            </form>
          </div>

          @if ($player->statValue('batting_style') || $player->statValue('bowling_style'))
            <div class="au-profile-style-card">
              @if ($player->statValue('batting_style'))
                <div><em>Batting</em><strong>{{ $player->statValue('batting_style') }}</strong></div>
              @endif
              @if ($player->statValue('bowling_style'))
                <div><em>Bowling</em><strong>{{ $player->statValue('bowling_style') }}</strong></div>
              @endif
            </div>
          @endif
        </aside>
      </div>

@include('auction.partials.operator-shell-end')
<script src="/auction-ui.js"></script>
<script>
(function () {
  document.querySelectorAll('[data-profile-tab]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const tab = btn.dataset.profileTab;
      document.querySelectorAll('[data-profile-tab]').forEach((b) => {
        b.classList.toggle('is-active', b.dataset.profileTab === tab);
      });
      document.querySelectorAll('[data-profile-panel]').forEach((panel) => {
        const show = panel.dataset.profilePanel === tab;
        panel.classList.toggle('is-active', show);
        panel.hidden = !show;
      });
    });
  });
})();
</script>
</body>
</html>
