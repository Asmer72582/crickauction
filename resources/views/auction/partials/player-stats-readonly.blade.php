@php
  $data = $player->data ?? [];
  $matches = $data['matches'] ?? null;
  $runs = $data['runs'] ?? null;
  $highest = $data['highest_score'] ?? null;
  $wickets = $data['wickets'] ?? null;
  $average = $data['average'] ?? null;
  $strikeRate = $data['strike_rate'] ?? null;
  $economy = $data['economy'] ?? null;
  $bestBowling = $data['best_bowling'] ?? null;
@endphp
<div class="au-profile-stats-card">
  <div class="au-profile-stats-tabs" role="tablist">
    <button type="button" class="au-profile-stats-tab is-active" data-profile-tab="batting">Batting</button>
    <button type="button" class="au-profile-stats-tab" data-profile-tab="bowling">Bowling</button>
  </div>

  <div class="au-profile-stats-panel is-active" data-profile-panel="batting">
    <div class="au-profile-stat-grid">
      <div class="au-profile-stat"><em>Matches</em><strong>{{ $matches ?: '—' }}</strong></div>
      <div class="au-profile-stat"><em>Runs</em><strong>{{ $runs ?: '—' }}</strong></div>
      <div class="au-profile-stat"><em>Highest</em><strong>{{ $highest ?: '—' }}</strong></div>
      <div class="au-profile-stat au-profile-stat-accent"><em>Average</em><strong>{{ $average ?: '—' }}</strong></div>
      <div class="au-profile-stat au-profile-stat-accent"><em>Strike Rate</em><strong>{{ $strikeRate ?: '—' }}</strong></div>
    </div>
  </div>

  <div class="au-profile-stats-panel" data-profile-panel="bowling" hidden>
    <div class="au-profile-stat-grid">
      <div class="au-profile-stat"><em>Matches</em><strong>{{ $matches ?: '—' }}</strong></div>
      <div class="au-profile-stat"><em>Wickets</em><strong>{{ $wickets ?: '—' }}</strong></div>
      <div class="au-profile-stat au-profile-stat-accent"><em>Economy</em><strong>{{ $economy ?: '—' }}</strong></div>
      <div class="au-profile-stat au-profile-stat-accent"><em>Best</em><strong>{{ $bestBowling ?: '—' }}</strong></div>
    </div>
  </div>
</div>
