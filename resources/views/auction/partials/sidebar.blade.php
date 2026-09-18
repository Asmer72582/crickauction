<aside class="dash-sidebar">
  <div class="dash-brand">
    <div class="dash-avatar">C</div>
    <div>
      <strong>{{ \Illuminate\Support\Str::limit($tournament->name, 18) }}</strong>
      <span>Operator Desk</span>
    </div>
  </div>
  <nav class="dash-nav">
    <a href="/tournaments/{{ $tournament->id }}/matches" @class(['active' => request()->routeIs('tournaments.matches')])>Matches</a>
    <a href="/tournaments/{{ $tournament->id }}/auction" @class(['active' => request()->routeIs('tournaments.auction')])>Auction Hub</a>
    <a href="/tournaments/{{ $tournament->id }}/auction/registration-form" @class(['active' => request()->routeIs('tournaments.registration-form.*')])>Player Registration</a>
    <a href="/tournaments/{{ $tournament->id }}/auction/players" @class(['active' => request()->routeIs('tournaments.registered-players*')])>Registered Players</a>
    <a href="/tournaments/{{ $tournament->id }}/auctions/create" @class(['active' => request()->routeIs('tournaments.auctions.create')])>Create Auction</a>
    <a href="/tournaments/{{ $tournament->id }}/broadcast">Broadcast</a>
    <a href="/">My Tournaments</a>
    <a href="/teams">Teams</a>
  </nav>
  <div class="dash-side-foot">Theme: {{ $theme['name'] ?? 'Arena Hub' }}</div>
</aside>
