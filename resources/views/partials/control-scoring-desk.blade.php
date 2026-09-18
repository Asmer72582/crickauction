<main class="content-area scoring-desk nd-main" id="scoring-desk">
  <nav class="nd-tabs" id="nd-tabs">
    <button type="button" class="nd-tab active" data-tab="center">Match Center</button>
    <button type="button" class="nd-tab" data-tab="scorecard">Scorecard</button>
    <button type="button" class="nd-tab" data-tab="graphs">Graph / Charts</button>
    <button type="button" class="nd-tab" data-tab="commentary">Commentary</button>
  </nav>

  <div class="nd-tab-panel active" id="tab-center">
    <div class="nd-center-grid">
      <section class="card nd-players-card">
        <div class="nd-bat-row">
          <div class="nd-player-card" id="card-striker">
            <div class="nd-av" id="av-striker">★</div>
            <div class="nd-p-body">
              <input type="text" id="striker-name" placeholder="Select batsman" />
              <div class="nd-p-score" id="stat-striker">0 (0)</div>
              <div class="nd-p-meta"><span id="st-4s">4s: 0</span> <span id="st-6s">6s: 0</span> <span id="st-sr">SR: 0</span></div>
              <div class="nd-p-actions">
                <button type="button" class="btn btn-sm btn-success" data-anim="player_card" data-role="striker">Show</button>
                <button type="button" class="btn btn-sm btn-secondary" id="btn-pick-striker">Select</button>
              </div>
            </div>
          </div>
          <button type="button" class="nd-strike-btn" id="btn-rotate" title="Change strike">⇄</button>
          <div class="nd-player-card" id="card-nonstriker">
            <div class="nd-av" id="av-nonstriker">NS</div>
            <div class="nd-p-body">
              <input type="text" id="nonstriker-name" placeholder="Select batsman" />
              <div class="nd-p-score" id="stat-nonstriker">0 (0)</div>
              <div class="nd-p-meta"><span id="ns-4s">4s: 0</span> <span id="ns-6s">6s: 0</span> <span id="ns-sr">SR: 0</span></div>
              <div class="nd-p-actions">
                <button type="button" class="btn btn-sm btn-success" data-anim="player_card" data-role="nonstriker">Show</button>
                <button type="button" class="btn btn-sm btn-secondary" id="btn-pick-nonstriker">Select</button>
              </div>
            </div>
          </div>
        </div>
        <div class="nd-mid-row">
          <div class="nd-partnership" id="nd-partnership">Partnership: 0 (0)</div>
          <button type="button" class="btn btn-sm btn-success" data-anim="partnership">Show</button>
          <div class="nd-last-out" id="nd-last-out">Last Out: —</div>
          <button type="button" class="btn btn-sm btn-success" data-anim="out">Last Out</button>
        </div>
        <div class="nd-bowl-row">
          <div class="nd-player-card nd-bowler-card">
            <div class="nd-av ball">●</div>
            <div class="nd-p-body">
              <input type="text" id="bowler-name" placeholder="Select bowler" />
              <div class="nd-p-score" id="stat-bowler">0.0 - 0 - 0 - 0</div>
              <div class="nd-p-meta"><span id="bw-econ">Econ: 0</span> <span id="bw-dots">Dots: 0</span></div>
              <div class="nd-p-actions">
                <button type="button" class="btn btn-sm btn-success" data-anim="player_card" data-role="bowler">Show</button>
                <button type="button" class="btn btn-sm btn-secondary" id="btn-pick-bowler">Select</button>
              </div>
            </div>
          </div>
          <div class="nd-this-over">
            <div class="this-over-bar">
              <span>THIS OVER <strong id="this-over-runs">0 runs</strong></span>
              <div id="this-over-display" class="this-over-balls"></div>
            </div>
            <button type="button" class="btn btn-primary btn-sm" id="btn-set-players">Apply players</button>
          </div>
        </div>
        <input type="hidden" id="keeper-name" />
        <div hidden>
          <span id="ck-striker-name"></span><span id="ck-nonstriker-name"></span><span id="ck-bowler-name"></span>
          <span id="ck-striker-stats"></span><span id="ck-nonstriker-stats"></span><span id="ck-keeper-name"></span>
          <span id="shot-zone-label"></span><span id="live-lines"></span>
        </div>
      </section>

      <section class="card card-scoring nd-keypad">
        <h2>Scoring</h2>
        <div class="score-pad pad-runs">
          <button type="button" class="pad-btn" data-action="dot">0</button>
          <button type="button" class="pad-btn run" data-action="run" data-runs="1">1</button>
          <button type="button" class="pad-btn run" data-action="run" data-runs="2">2</button>
          <button type="button" class="pad-btn run" data-action="run" data-runs="3">3</button>
          <button type="button" class="pad-btn four" data-action="run" data-runs="4">FOUR</button>
          <button type="button" class="pad-btn six" data-action="run" data-runs="6">SIX</button>
          <button type="button" class="pad-btn run" data-action="run" data-runs="5">5</button>
          <button type="button" class="pad-btn run" data-action="run" data-runs="7">7</button>
          <button type="button" class="pad-btn extra" data-action="legbye" data-runs="1">LB</button>
          <button type="button" class="pad-btn extra" data-action="bye" data-runs="1">BYE</button>
          <button type="button" class="pad-btn undo" id="btn-undo">UNDO</button>
          <button type="button" class="pad-btn extra" data-action="wide" data-extra="0">Wide</button>
          <button type="button" class="pad-btn extra" data-action="noball" data-runs="0">No Ball</button>
          <button type="button" class="pad-btn extra" data-action="penalty" data-runs="5">Penalty</button>
          <button type="button" class="pad-btn wicket" data-action="wicket" data-dismissal="OUT">OUT</button>
        </div>
        <div class="score-pad pad-wickets nd-dismissals">
          <button type="button" class="pad-btn wicket" data-action="wicket" data-dismissal="b">Bowled</button>
          <button type="button" class="pad-btn wicket" data-action="wicket" data-dismissal="c">Caught</button>
          <button type="button" class="pad-btn wicket" data-action="wicket" data-dismissal="lbw">LBW</button>
          <button type="button" class="pad-btn wicket" data-action="wicket" data-dismissal="run out">Run Out</button>
          <button type="button" class="pad-btn wicket" data-action="wicket" data-dismissal="st">Stumped</button>
        </div>
        <select id="dismissal-type" hidden>
          <option value="OUT">OUT</option>
          <option value="b">Bowled</option>
          <option value="c">Caught</option>
          <option value="lbw">LBW</option>
          <option value="run out">Run Out</option>
          <option value="st">Stumped</option>
        </select>
        <div class="nd-match-controls">
          <button type="button" class="btn btn-secondary" id="btn-end-innings">End 1st Innings</button>
          <button type="button" class="btn btn-danger" id="btn-walkover">Walkover / Abandon</button>
          <button type="button" class="btn btn-secondary" id="btn-rotate-2">Swap Strike</button>
          <button type="button" class="btn btn-primary" id="btn-end-match" hidden>End 2nd Innings</button>
          <button type="button" class="btn btn-danger" id="btn-reset">Reset</button>
        </div>
      </section>
    </div>

    <div class="nd-bottom-grid">
      <section class="card">
        <h2>Lower Scoreboard</h2>
        <div class="toggle-row">
          <label class="toggle-switch"><input type="checkbox" id="auto-loop-toggle" /> Automatic Loop</label>
          <label class="toggle-switch"><input type="checkbox" id="powerplay-toggle" /> Powerplay</label>
          <label class="toggle-switch"><input type="checkbox" id="visible-toggle" checked /> Show overlay</label>
        </div>
        <div class="field-row">
          <label>Commentator</label>
          <input type="text" id="commentator" class="wide" placeholder="Name" />
          <button type="button" class="btn btn-sm btn-success" id="btn-show-commentator">Show</button>
        </div>
        <div class="field-row">
          <label>Toss winner</label>
          <input type="text" id="toss-winner" class="wide" placeholder="Team name" />
        </div>
        <div class="field-row">
          <label>Elected</label>
          <select id="toss-decision">
            <option value="bat">bat</option>
            <option value="bowl">bowl</option>
          </select>
          <button type="button" class="btn btn-sm btn-secondary" id="btn-save-toss">Save toss</button>
          <button type="button" class="btn btn-sm btn-success" data-anim="toss">Show toss</button>
        </div>
      </section>

      <section class="card ko-card ko-card-slim">
        <h2>Knockout Stage</h2>
        <p class="hint">Set all bracket matches on the Knockout page. Use Show here to put it on stream.</p>
        <div class="ko-slim-summary" id="ko-slim-summary">
          <div class="ko-slim-row"><span>Title</span><strong id="ko-summary-title">KNOCKOUT ROUND</strong></div>
          <div class="ko-slim-row"><span>Format</span><strong id="ko-summary-format">8 teams</strong></div>
          <div class="ko-slim-row"><span>Filled</span><strong id="ko-summary-filled">0 / 0 slots</strong></div>
        </div>
        <div class="ko-show-bar">
          <a class="btn btn-secondary" id="ko-manage-link" href="/control/knockout?room={{ $room ?? '' }}">Manage Knockout Matches</a>
          <button type="button" class="btn-ko-primary" id="btn-ko-show">Show Knockout Overlay</button>
        </div>
      </section>

      <section class="card">
        <h2>Theme / Other Panels</h2>
        <div class="match-starter-bar">
          <div>
            <strong>Match Starter</strong>
            <span class="hint" id="match-starter-status">Plays Team VS → Toss → Squad A → Squad B → Tournament → Commentator → Field on one click</span>
          </div>
          <button type="button" class="btn btn-accent" id="btn-match-starter-panel">Start Match Sequence</button>
        </div>
        <div class="nd-gfx-cats">
          <div class="nd-gfx-cat">
            <label>Match cards</label>
            <div class="nd-gfx-row">
              <select id="gfx-cat-match" class="nd-gfx-select">
                <option value="team_vs_team">Team vs Team</option>
                <option value="toss">Toss Details</option>
                <option value="match_summary">Match Summary</option>
                <option value="live_scorecard">Live Scorecard</option>
                <option value="both_squads">Both Squads</option>
                <option value="team_lineup|A">Team A Squad</option>
                <option value="team_lineup|B">Team B Squad</option>
                <option value="tournament_name">Tournament Name</option>
                <option value="knockout_round">Knockout Round</option>
                <option value="blackboard">Black Board</option>
              </select>
              <button type="button" class="btn btn-sm btn-success" data-gfx-fire="gfx-cat-match">Show</button>
            </div>
          </div>
          <div class="nd-gfx-cat">
            <label>Innings &amp; result</label>
            <div class="nd-gfx-row">
              <select id="gfx-cat-innings" class="nd-gfx-select">
                <option value="batting_summary|A">Batting Summary A</option>
                <option value="batting_summary|B">Batting Summary B</option>
                <option value="bowling_summary|A">Bowling Summary A</option>
                <option value="bowling_summary|B">Bowling Summary B</option>
                <option value="partnership">Partnership</option>
                <option value="need_to_win">Need to Win</option>
                <option value="innings_break">Innings Break</option>
                <option value="winner">Winner</option>
                <option value="best_batsman">Best Batsman</option>
                <option value="player_card">Player Card</option>
                <option value="instant_show">Instant Show</option>
              </select>
              <button type="button" class="btn btn-sm btn-success" data-gfx-fire="gfx-cat-innings">Show</button>
            </div>
          </div>
          <div class="nd-gfx-cat">
            <label>Hot points</label>
            <div class="nd-gfx-row">
              <select id="gfx-cat-hot" class="nd-gfx-select">
                <option value="instant_show">Instant Show</option>
                <option value="player_card">Player Card</option>
                <option value="partnership">Partnership</option>
                <option value="boundaries_counter|match">Match 4s / 6s</option>
                <option value="boundaries_counter|tournament">Tournament 4s / 6s</option>
              </select>
              <button type="button" class="btn btn-sm btn-success" data-gfx-fire="gfx-cat-hot">Show</button>
            </div>
          </div>
          <div class="nd-gfx-cat">
            <label>Boundaries</label>
            <div class="nd-gfx-row">
              <select id="gfx-cat-bd" class="nd-gfx-select">
                <option value="boundaries_counter|match">Match 4s / 6s</option>
                <option value="boundaries_counter|tournament">Tournament 4s / 6s</option>
              </select>
              <button type="button" class="btn btn-sm btn-success" data-gfx-fire="gfx-cat-bd">Show</button>
            </div>
          </div>
          <div class="nd-gfx-cat">
            <label>Live events</label>
            <div class="nd-gfx-row">
              <select id="gfx-cat-events" class="nd-gfx-select">
                <option value="four">FOUR</option>
                <option value="six">SIX</option>
                <option value="wicket">WICKET</option>
                <option value="out">OUT</option>
                <option value="milestone|50">50 Milestone</option>
                <option value="milestone|100">100 Milestone</option>
                <option value="field_position">Field Position</option>
              </select>
              <button type="button" class="btn btn-sm btn-success" data-gfx-fire="gfx-cat-events">Show</button>
            </div>
          </div>
        </div>
        <div class="toggle-row nd-gfx-toggles">
          <label class="toggle-switch"><input type="checkbox" id="auto-gfx-toggle" checked /> 4s/6s/Wicket Graphics</label>
          <label class="toggle-switch"><input type="checkbox" id="boundaries-counter-toggle" checked /> Auto 4s/6s after FOUR/SIX</label>
          <label class="toggle-switch"><input type="checkbox" id="show-logo-toggle" checked /> Stream Logo</label>
        </div>
        <div class="field-row">
          <label>Custom Msg</label>
          <input type="text" id="custom-message" class="wide" placeholder="This is a test msg" />
          <button type="button" class="btn btn-sm btn-success" id="btn-custom-msg" data-anim="custom">Show</button>
        </div>
      </section>

      <section class="card">
        <h2>Team Squad Settings</h2>
        <p class="field-hint">Add players or import a saved team. Squads are saved to the library automatically and can be reused anytime.</p>
        <div class="nd-squads">
          <div class="nd-squad-col">
            <h3 id="squad-a-title">Team A</h3>
            <div class="nd-squad-lib-row">
              <select id="import-team-a" class="wide">
                <option value="">Import saved team…</option>
              </select>
              <button type="button" class="btn btn-sm btn-secondary" id="btn-import-team-a">Import</button>
              <button type="button" class="btn btn-sm btn-success" id="btn-save-team-a" title="Save this squad to library">Save</button>
            </div>
            <div class="field-row">
              <input type="text" id="squad-a-input" placeholder="Enter player name" />
              <button type="button" class="btn btn-sm btn-danger" id="btn-add-squad-a">Add</button>
            </div>
            <ul class="nd-squad-list" id="squad-a-list"></ul>
          </div>
          <div class="nd-squad-col">
            <h3 id="squad-b-title">Team B</h3>
            <div class="nd-squad-lib-row">
              <select id="import-team-b" class="wide">
                <option value="">Import saved team…</option>
              </select>
              <button type="button" class="btn btn-sm btn-secondary" id="btn-import-team-b">Import</button>
              <button type="button" class="btn btn-sm btn-success" id="btn-save-team-b" title="Save this squad to library">Save</button>
            </div>
            <div class="field-row">
              <input type="text" id="squad-b-input" placeholder="Enter player name" />
              <button type="button" class="btn btn-sm btn-danger" id="btn-add-squad-b">Add</button>
            </div>
            <ul class="nd-squad-list" id="squad-b-list"></ul>
          </div>
        </div>

        <div class="nd-squad-editor" id="squad-editor" hidden>
          <div class="nd-squad-editor-head">
            <strong id="squad-ed-title">Player profile</strong>
            <button type="button" class="btn btn-sm btn-secondary" id="btn-squad-ed-close">Close</button>
          </div>
          <div class="nd-squad-editor-grid">
            <div class="field-row">
              <label>Name</label>
              <input type="text" id="squad-ed-name" class="wide" />
            </div>
            <div class="field-row">
              <label>Role</label>
              <select id="squad-ed-role">
                <option value="batsman">Batsman</option>
                <option value="bowler">Bowler</option>
                <option value="allrounder">All-rounder</option>
                <option value="wk">Wicket-keeper</option>
              </select>
            </div>
            <div class="field-row">
              <label>Batting</label>
              <input type="text" id="squad-ed-bat-style" placeholder="RHB / LHB" />
            </div>
            <div class="field-row">
              <label>Bowling</label>
              <input type="text" id="squad-ed-bowl-style" placeholder="RM / RF / SLO" />
            </div>
            <div class="field-row logo-upload-row">
              <label>Avatar</label>
              <input type="url" id="squad-ed-avatar" class="wide" placeholder="Image URL or upload →" />
              <input type="file" id="squad-ed-avatar-file" accept="image/*" class="logo-file" />
              <img id="squad-ed-avatar-preview" class="logo-preview" alt="" hidden />
            </div>
          </div>
          <h3 class="nd-squad-hist-title">Career / history</h3>
          <div class="nd-squad-editor-grid">
            <div class="field-row"><label>Matches</label><input type="number" id="squad-ed-matches" min="0" class="narrow" /></div>
            <div class="field-row"><label>Runs</label><input type="number" id="squad-ed-runs" min="0" class="narrow" /></div>
            <div class="field-row"><label>Wickets</label><input type="number" id="squad-ed-wickets" min="0" class="narrow" /></div>
            <div class="field-row"><label>Average</label><input type="text" id="squad-ed-avg" class="narrow" placeholder="e.g. 42.5" /></div>
            <div class="field-row"><label>Best</label><input type="text" id="squad-ed-best" class="narrow" placeholder="e.g. 88* or 4/12" /></div>
            <div class="field-row"><label>SR</label><input type="text" id="squad-ed-sr" class="narrow" placeholder="e.g. 138.2" /></div>
          </div>
          <div class="nd-squad-editor-actions">
            <button type="button" class="btn btn-primary" id="btn-squad-ed-save">Save profile</button>
            <button type="button" class="btn btn-success" id="btn-squad-ed-show">Show player card</button>
          </div>
        </div>
      </section>
    </div>

    <section class="card nd-field-card">
      <h2>Field Position</h2>
      <p class="field-hint">Circular field map (same as overlay). Click a spot, then pick a bowling-team player. Drag dots to reposition.</p>
      <div class="nd-field-toolbar">
        <label>Zone
          <select id="field-zone">
            <option value="Standard">Standard</option>
            <option value="Powerplay">Powerplay</option>
            <option value="Attacking">Attacking</option>
            <option value="Defensive">Defensive</option>
            <option value="Death overs">Death overs</option>
          </select>
        </label>
        <button type="button" class="btn btn-sm btn-secondary" id="btn-field-fill">Fill from bowling squad</button>
        <button type="button" class="btn btn-sm btn-secondary" id="btn-field-reset">Reset layout</button>
        <button type="button" class="btn btn-sm btn-primary" id="btn-field-save">Save field</button>
        <button type="button" class="btn btn-sm btn-success" data-anim="field_position">Show on overlay</button>
      </div>
      <div class="nd-field-bowl-bar">
        <strong id="field-bowl-title">Bowling team</strong>
        <div class="nd-field-bowl-list" id="field-bowl-list"></div>
      </div>
      <div class="nd-field-wrap">
        <div class="nd-field-circle" id="field-oval">
          <div class="nd-field-ring"></div>
          <div class="nd-field-pitch"></div>
        </div>
        <ul class="nd-field-list" id="field-list"></ul>
      </div>
    </section>

    <section class="card nd-meta-card">
      <h2>Match Info &amp; Branding</h2>
      <div class="nd-meta-grid">
        <div class="field-row"><label>Title</label><input type="text" id="match-title" class="wide" /></div>
        <div class="field-row">
          <label>Match No.</label><input type="text" id="match-no" class="narrow" />
          <label>Overs</label><input type="number" id="total-overs" class="narrow" min="1" max="50" value="20" />
        </div>
        <div class="field-row"><label>Team A</label><input type="text" id="team-a-name" class="wide" /></div>
        <div class="field-row logo-upload-row">
          <label>Team A logo</label>
          <input type="url" id="team-a-logo" class="wide" placeholder="https://… or upload →" />
          <input type="file" id="team-a-logo-file" accept="image/*" class="logo-file" data-slot="teamA" data-target="team-a-logo" />
          <img id="team-a-logo-preview" class="logo-preview" alt="" hidden />
        </div>
        <div class="field-row"><label>Team B</label><input type="text" id="team-b-name" class="wide" /></div>
        <div class="field-row logo-upload-row">
          <label>Team B logo</label>
          <input type="url" id="team-b-logo" class="wide" placeholder="https://… or upload →" />
          <input type="file" id="team-b-logo-file" accept="image/*" class="logo-file" data-slot="teamB" data-target="team-b-logo" />
          <img id="team-b-logo-preview" class="logo-preview" alt="" hidden />
        </div>
        <div class="field-row logo-upload-row">
          <label>Organizer logo</label>
          <input type="url" id="organizer-logo" class="wide" placeholder="Shown on Team vs Team & summaries" />
          <input type="file" id="organizer-logo-file" accept="image/*" class="logo-file" data-slot="organizer" data-target="organizer-logo" />
          <img id="organizer-logo-preview" class="logo-preview" alt="" hidden />
        </div>
        <div class="field-row logo-upload-row">
          <label>Streamer logo</label>
          <input type="url" id="streamer-logo" class="wide" placeholder="Right scoreboard / LIVE badge" />
          <input type="file" id="streamer-logo-file" accept="image/*" class="logo-file" data-slot="streamer" data-target="streamer-logo" />
          <img id="streamer-logo-preview" class="logo-preview" alt="" hidden />
        </div>
        <div class="field-row logo-upload-row">
          <label>Player avatar</label>
          <input type="url" id="player-avatar" class="wide" />
          <input type="file" id="player-avatar-file" accept="image/*" class="logo-file" data-slot="player" data-target="player-avatar" />
          <img id="player-avatar-preview" class="logo-preview" alt="" hidden />
        </div>
        <div class="toggle-row">
          <span>Batting:</span>
          <label><input type="radio" name="batting" value="A" id="batting-a" /> Team A</label>
          <label><input type="radio" name="batting" value="B" id="batting-b" /> Team B</label>
        </div>
        <div class="field-row">
          <label>Result</label><input type="text" id="result-text" class="wide" />
          <button type="button" class="btn btn-secondary" id="btn-set-result">Set</button>
        </div>
        <button type="button" class="btn btn-secondary" id="btn-save-meta">Save match info</button>
      </div>
    </section>
  </div>

  <div class="nd-tab-panel" id="tab-scorecard" hidden>
    <section class="card"><h2>Live Scorecard</h2><div id="scorecard" class="scorecard"></div></section>
    <div class="nd-scorecard-dual" id="scorecard-dual"></div>
  </div>

  <div class="nd-tab-panel" id="tab-graphs" hidden>
    <div class="nd-graphs-grid">
      <section class="card nd-chart-card"><h2>Manhattan</h2><canvas id="chart-manhattan" height="180"></canvas></section>
      <section class="card nd-chart-card"><h2>Worm</h2><canvas id="chart-worm" height="180"></canvas></section>
      <section class="card nd-chart-card"><h2>Run Rate</h2><canvas id="chart-rr" height="180"></canvas></section>
      <section class="card nd-chart-card"><h2>Partnerships</h2><canvas id="chart-partnership" height="180"></canvas></section>
    </div>
    <p class="field-hint">Charts update from the ball log as you score.</p>
  </div>

  <div class="nd-tab-panel" id="tab-commentary" hidden>
    <section class="card">
      <h2>Commentary</h2>
      <div class="field-row">
        <input type="text" id="commentary-input" class="wide" placeholder="Add ball commentary…" />
        <button type="button" class="btn btn-success" id="btn-add-commentary">Add</button>
      </div>
      <ul class="nd-commentary-list" id="commentary-list"></ul>
    </section>
  </div>
</main>

<div class="nd-modal" id="player-modal" hidden>
  <div class="nd-modal-card">
    <div class="nd-modal-head">
      <strong id="player-modal-title">Select Player</strong>
      <button type="button" class="modal-close" id="player-modal-close">&times;</button>
    </div>
    <div class="field-row">
      <input type="text" id="player-modal-new" placeholder="Enter player name" />
      <button type="button" class="btn btn-danger" id="player-modal-add">Add New</button>
    </div>
    <input type="text" id="player-modal-search" placeholder="Search player" class="wide" />
    <ul class="nd-modal-list" id="player-modal-list"></ul>
  </div>
</div>
