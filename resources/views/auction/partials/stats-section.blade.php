@php
  $old = fn (string $key) => old($key, '');
@endphp
<div class="au-stats-board" id="stats-board">
  <p class="au-stats-intro">Enter your core numbers — batting &amp; bowling stats are calculated automatically.</p>

  <div class="au-stats-input-grid">
    <label class="au-stat-input">
      <span class="au-stat-input-label">Matches</span>
      <input type="number" name="matches" id="stat-matches" min="0" step="1" value="{{ $old('matches') }}" placeholder="0" inputmode="numeric" />
    </label>
    <label class="au-stat-input">
      <span class="au-stat-input-label">Runs</span>
      <input type="number" name="runs" id="stat-runs" min="0" step="1" value="{{ $old('runs') }}" placeholder="0" inputmode="numeric" />
    </label>
    <label class="au-stat-input">
      <span class="au-stat-input-label">Highest Score</span>
      <input type="number" name="highest_score" id="stat-highest_score" min="0" step="1" value="{{ $old('highest_score') }}" placeholder="0" inputmode="numeric" />
    </label>
    <label class="au-stat-input">
      <span class="au-stat-input-label">Wickets</span>
      <input type="number" name="wickets" id="stat-wickets" min="0" step="1" value="{{ $old('wickets') }}" placeholder="0" inputmode="numeric" />
    </label>
  </div>

  <input type="hidden" name="average" id="calc-average" value="{{ $old('average') }}" />
  <input type="hidden" name="strike_rate" id="calc-strike_rate" value="{{ $old('strike_rate') }}" />
  <input type="hidden" name="economy" id="calc-economy" value="{{ $old('economy') }}" />
  <input type="hidden" name="best_bowling" id="calc-best_bowling" value="{{ $old('best_bowling') }}" />

  <div class="au-stats-tabs" role="tablist">
    <button type="button" class="au-stats-tab is-active" data-stats-tab="batting" role="tab" aria-selected="true">Batting</button>
    <button type="button" class="au-stats-tab" data-stats-tab="bowling" role="tab" aria-selected="false">Bowling</button>
  </div>

  <div class="au-stats-panel is-active" data-stats-panel="batting" role="tabpanel">
    <div class="au-stats-rows">
      <div class="au-stats-row">
        <span class="au-stats-val" id="disp-matches-bat">—</span>
        <span class="au-stats-mid">Matches</span>
        <span class="au-stats-val" id="disp-runs">—</span>
      </div>
      <div class="au-stats-row">
        <span class="au-stats-val au-stats-val-muted" id="disp-runs-ref">—</span>
        <span class="au-stats-mid">Runs</span>
        <span class="au-stats-val" id="disp-highest">—</span>
      </div>
      <div class="au-stats-row au-stats-row-highlight">
        <span class="au-stats-val" id="disp-average">—</span>
        <span class="au-stats-mid">Average</span>
        <span class="au-stats-val au-stats-val-accent" id="disp-strike_rate">—</span>
      </div>
      <div class="au-stats-row au-stats-row-sub">
        <span></span>
        <span class="au-stats-mid au-stats-mid-sub">Strike Rate</span>
        <span></span>
      </div>
    </div>
  </div>

  <div class="au-stats-panel" data-stats-panel="bowling" role="tabpanel" hidden>
    <div class="au-stats-rows">
      <div class="au-stats-row">
        <span class="au-stats-val" id="disp-matches-bowl">—</span>
        <span class="au-stats-mid">Matches</span>
        <span class="au-stats-val" id="disp-wickets">—</span>
      </div>
      <div class="au-stats-row au-stats-row-highlight">
        <span class="au-stats-val au-stats-val-accent" id="disp-economy">—</span>
        <span class="au-stats-mid">Economy</span>
        <span class="au-stats-val" id="disp-best_bowling">—</span>
      </div>
      <div class="au-stats-row au-stats-row-sub">
        <span></span>
        <span class="au-stats-mid au-stats-mid-sub">Best Bowling</span>
        <span></span>
      </div>
      <div class="au-stats-row">
        <span class="au-stats-val au-stats-val-muted" id="disp-wickets-ref">—</span>
        <span class="au-stats-mid">Total Wickets</span>
        <span class="au-stats-val au-stats-val-muted">Career</span>
      </div>
    </div>
  </div>

  <p class="au-stats-note">Calculated from your entries · used on your player profile</p>
  @error('matches')<span class="au-error">{{ $message }}</span>@enderror
  @error('runs')<span class="au-error">{{ $message }}</span>@enderror
  @error('highest_score')<span class="au-error">{{ $message }}</span>@enderror
  @error('wickets')<span class="au-error">{{ $message }}</span>@enderror
</div>
