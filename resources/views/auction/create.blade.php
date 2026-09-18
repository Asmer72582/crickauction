<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Create Auction — Cricket Overlay</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/dashboard.css">
  <link rel="stylesheet" href="/app-shell.css">
  <link rel="stylesheet" href="/auction.css?v=24">
</head>
<body class="dash-body">
@php
  $selectedTournament = $selectedTournament ?? $tournament ?? null;
  $navSection = 'auctions';
  $pageTitle = 'Create Auction';
  $topbarNote = 'Auction center';
  $brandSubline = 'Auction setup';
  $sidebarFoot = $selectedTournament?->name ?? 'Standalone auction';
  $pageClass = 'app-page-desk';
  $topbarMeta = array_values(array_filter([
    $selectedTournament ? 'Linked: '.$selectedTournament->name : 'No tournament linked',
  ]));
  $secondaryAction = ['label' => 'All Auctions', 'url' => route('auctions.index')];
@endphp
@include('partials.admin-shell', compact(
  'navSection',
  'pageTitle',
  'topbarNote',
  'brandSubline',
  'sidebarFoot',
  'topbarMeta',
  'secondaryAction',
  'pageClass'
))

      <form method="post" action="{{ route('auctions.store') }}" class="au-wizard-wrap au-create-flow app-desk" id="auction-create-form" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="tournament_id" id="auction-tournament-id" value="{{ $selectedTournament?->id }}" data-initial="{{ $selectedTournament?->id }}" />
        <input type="hidden" name="registration_mode" id="registration-mode-input" value="both" />

        <div class="au-stepper" id="au-stepper">
          <div class="au-stepper-step active" data-step="1"><div class="au-stepper-dot">1</div><div class="au-stepper-label">Basic</div></div>
          <div class="au-stepper-step" data-step="2"><div class="au-stepper-dot">2</div><div class="au-stepper-label">Registration</div></div>
          <div class="au-stepper-step" data-step="3"><div class="au-stepper-dot">3</div><div class="au-stepper-label">Teams</div></div>
          <div class="au-stepper-step" data-step="4"><div class="au-stepper-dot">4</div><div class="au-stepper-label">Rules</div></div>
          <div class="au-stepper-step" data-step="5"><div class="au-stepper-dot">5</div><div class="au-stepper-label">Players</div></div>
          <div class="au-stepper-step" data-step="6"><div class="au-stepper-dot">6</div><div class="au-stepper-label">Review</div></div>
        </div>

        <div class="au-card au-create-card">
          <section class="au-step-panel active" data-step="1">
            <div class="au-form-field">
              <span>Auction name</span>
              <input type="text" name="name" id="auction-name-input" required placeholder="e.g. Summer League Auction 2026" autocomplete="off" />
            </div>
            <div class="au-form-field">
              <span>Link tournament <em class="au-optional">(optional)</em></span>
              <select name="tournament_picker" id="auction-tournament-picker">
                <option value="">No tournament — auction only</option>
                @foreach ($tournaments as $item)
                  <option value="{{ $item->id }}" @selected($selectedTournament && $selectedTournament->id === $item->id)>{{ $item->name }}</option>
                @endforeach
              </select>
              <small class="au-help">Optional. Link only if you want approved form players from a tournament.</small>
            </div>
          </section>

          <section class="au-step-panel" data-step="2">
            <p class="au-muted" style="margin-bottom:12px">Select one or both player sources. You can use the public form and still add players manually.</p>
            <div class="au-flow-choice-grid">
              <label class="au-flow-choice is-active" data-mode-card="form">
                <input type="checkbox" name="registration_sources[]" value="form" id="mode-form" checked />
                <strong>Use Registration Form</strong>
                <span>Share a unique public form for this auction and approve players on its Verify tab.</span>
              </label>
              <label class="au-flow-choice is-active" data-mode-card="manual">
                <input type="checkbox" name="registration_sources[]" value="manual" id="mode-manual" checked />
                <strong>Manual Player Entry</strong>
                <span>Add players one card at a time with optional photos — works without a tournament.</span>
              </label>
            </div>
            <div class="au-create-note">
              <strong>{{ $selectedTournament?->name ?? 'Standalone auction' }}</strong>
              <span>
                This auction gets its own players and registration link. Form sign-ups and admin adds stay on this auction only.
              </span>
            </div>
          </section>

          <section class="au-step-panel" data-step="3">
            <p class="au-muted" style="margin-bottom:14px">Add teams one by one with optional logos. At least 2 teams required.</p>
            <div class="au-entry-stack" id="teams-list"></div>
            <div class="au-entry-composer" id="team-composer">
              <div class="au-team-composer-row">
                <label class="au-photo-upload au-photo-upload-sm" for="team-logo-input">
                  <img id="team-logo-preview" alt="" hidden />
                  <span id="team-logo-placeholder">Logo</span>
                  <input type="file" id="team-logo-input" accept="image/*" />
                </label>
                <div class="au-entry-composer-grid au-entry-composer-team">
                  <div class="au-form-field">
                    <span>Team name</span>
                    <input type="text" id="team-name-input" placeholder="e.g. Rising Stars" autocomplete="off" />
                  </div>
                  <div class="au-form-field">
                    <span>Short</span>
                    <input type="text" id="team-short-input" placeholder="RS" maxlength="12" autocomplete="off" />
                  </div>
                </div>
              </div>
              <button type="button" class="au-btn au-btn-primary" id="btn-add-team">Add team</button>
            </div>
            <div id="teams-hidden" hidden></div>
          </section>

          <section class="au-step-panel" data-step="4">
            <div class="au-rules-grid">
              <div class="au-rules-group">
                <h4><img src="/assets/icons/cricket/trophy.svg" alt="" /> Purse & pricing</h4>
                <div class="au-form-field"><span><img class="au-field-cricket-ico" src="/assets/icons/cricket/trophy.svg" alt="" /> Starting purse (₹)</span><input type="number" name="rules[starting_purse]" id="rule-purse" value="5000000" min="0" /></div>
                <div class="au-form-field"><span><img class="au-field-cricket-ico" src="/assets/icons/cricket/bat.svg" alt="" /> Default base price (₹)</span><input type="number" name="rules[default_base_price]" id="rule-base" value="20000" min="0" /></div>
                <div class="au-form-field"><span><img class="au-field-cricket-ico" src="/assets/icons/cricket/strike.svg" alt="" /> Bid increment (₹)</span><input type="number" name="rules[bid_increment]" id="rule-inc" value="10000" min="0" /></div>
              </div>
              <div class="au-rules-group">
                <h4><img src="/assets/icons/cricket/cricket.svg" alt="" /> Squad size</h4>
                <div class="au-form-field"><span><img class="au-field-cricket-ico" src="/assets/icons/cricket/cricket.svg" alt="" /> Min squad</span><input type="number" name="rules[min_squad]" id="rule-min" value="11" min="1" max="40" /></div>
                <div class="au-form-field"><span><img class="au-field-cricket-ico" src="/assets/icons/cricket/cricket.svg" alt="" /> Players per team</span><input type="number" name="rules[max_squad]" id="rule-max" value="25" min="1" max="50" /></div>
                <div class="au-form-field"><span><img class="au-field-cricket-ico" src="/assets/icons/cricket/age.svg" alt="" /> Team count target</span><input type="number" name="rules[team_count]" id="rule-teams" value="8" min="2" max="20" /></div>
              </div>
              <div class="au-rules-group">
                <h4><img src="/assets/icons/cricket/ball.svg" alt="" /> Timer & bidding</h4>
                <div class="au-form-field"><span><img class="au-field-cricket-ico" src="/assets/icons/cricket/ball.svg" alt="" /> Bid timer (seconds)</span><input type="number" name="rules[bid_timer_seconds]" id="rule-timer" value="30" min="5" max="300" /></div>
                <div class="au-form-field"><span><img class="au-field-cricket-ico" src="/assets/icons/cricket/strike.svg" alt="" /> Bid extension (seconds)</span><input type="number" name="rules[bid_extension_seconds]" id="rule-ext" value="10" min="0" max="60" /></div>
                <input type="hidden" name="rules[allow_reauction]" value="0" />
                <label class="au-check-chip au-rules-check">
                  <input type="checkbox" name="rules[allow_reauction]" id="rule-reauction" value="1" checked />
                  <span>Allow re-auction of unsold players</span>
                </label>
              </div>
              <div class="au-rules-group">
                <h4><img src="/assets/icons/cricket/trophy.svg" alt="" /> Broadcast labels</h4>
                <div class="au-form-field"><span><img class="au-field-cricket-ico" src="/assets/icons/cricket/trophy.svg" alt="" /> League title</span><input type="text" name="rules[league_title]" id="rule-league" placeholder="e.g. Mega Cricket League" /></div>
                <div class="au-form-field"><span><img class="au-field-cricket-ico" src="/assets/icons/cricket/cricket.svg" alt="" /> Sponsor line</span><input type="text" name="rules[sponsor_line]" id="rule-sponsor" placeholder="Optional sponsor text" /></div>
              </div>
            </div>
          </section>

          <section class="au-step-panel" data-step="5">
            <div class="au-player-source" data-player-source="form">
              <div class="au-create-note" style="margin-top:8px">
                <strong>Unique form link</strong>
                <span>After create, open the Verify tab to copy this auction’s registration link. Players who fill it appear only here — not on other auctions.</span>
              </div>
            </div>

            <div class="au-player-source" data-player-source="manual">
              <p class="au-muted" style="margin:16px 0 12px">Enter one player card at a time.</p>
              <div class="au-player-card-rail" id="manual-players-list"></div>
              <div class="au-player-card-composer" id="player-composer">
                <div class="au-player-card-sheet">
                  <label class="au-photo-upload" for="player-photo-input">
                    <img id="player-photo-preview" alt="" hidden />
                    <span id="player-photo-placeholder">Photo</span>
                    <input type="file" id="player-photo-input" accept="image/*" />
                  </label>
                  <div class="au-player-card-fields">
                    <div class="au-form-field">
                      <span>Player name</span>
                      <input type="text" id="player-name-input" placeholder="Full name" autocomplete="off" />
                    </div>
                    <div class="au-form-field">
                      <span><img class="au-field-cricket-ico" src="/assets/icons/cricket/cricket.svg" alt="" /> Playing role <em class="au-optional">(up to 2)</em></span>
                      <div class="au-role-grid au-create-role-grid" id="player-role-group">
                        @foreach ([
                          ['label' => 'Batter', 'icon' => 'bat'],
                          ['label' => 'Bowler', 'icon' => 'ball'],
                          ['label' => 'All-rounder', 'icon' => 'cricket'],
                          ['label' => 'Wicketkeeper', 'icon' => 'trophy'],
                        ] as $role)
                          <label class="au-role-card">
                            <input type="checkbox" name="player_role_picker" value="{{ $role['label'] }}" />
                            <span class="au-role-icon" aria-hidden="true">
                              <img class="au-role-icon-img" src="/assets/icons/cricket/{{ $role['icon'] }}.svg" alt="" />
                            </span>
                            <span class="au-role-name">{{ $role['label'] }}</span>
                          </label>
                        @endforeach
                      </div>
                    </div>
                    <div class="au-player-card-grid">
                      <div class="au-form-field"><span><img class="au-field-cricket-ico" src="/assets/icons/cricket/trophy.svg" alt="" /> Base price</span><input type="number" id="player-base-input" value="20000" min="0" /></div>
                      <div class="au-form-field">
                        <span><img class="au-field-cricket-ico" src="/assets/icons/cricket/bat.svg" alt="" /> Batting</span>
                        <select id="player-bat-input">
                          <option value="">Select</option>
                          <option value="Right Hand">Right Hand</option>
                          <option value="Left Hand">Left Hand</option>
                        </select>
                      </div>
                      <div class="au-form-field">
                        <span><img class="au-field-cricket-ico" src="/assets/icons/cricket/ball.svg" alt="" /> Bowling</span>
                        <select id="player-bowl-input">
                          <option value="">Select</option>
                          <option value="Right Arm Fast">Right Arm Fast</option>
                          <option value="Right Arm Medium">Right Arm Medium</option>
                          <option value="Right Arm Spin">Right Arm Spin</option>
                          <option value="Left Arm Fast">Left Arm Fast</option>
                          <option value="Left Arm Medium">Left Arm Medium</option>
                          <option value="Left Arm Spin">Left Arm Spin</option>
                        </select>
                      </div>
                      <div class="au-form-field"><span><img class="au-field-cricket-ico" src="/assets/icons/cricket/age.svg" alt="" /> Matches</span><input type="number" id="player-matches-input" min="0" /></div>
                      <div class="au-form-field"><span><img class="au-field-cricket-ico" src="/assets/icons/cricket/bat.svg" alt="" /> Runs</span><input type="number" id="player-runs-input" min="0" /></div>
                      <div class="au-form-field"><span><img class="au-field-cricket-ico" src="/assets/icons/cricket/strike.svg" alt="" /> Highest</span><input type="number" id="player-hs-input" min="0" /></div>
                      <div class="au-form-field"><span><img class="au-field-cricket-ico" src="/assets/icons/cricket/ball.svg" alt="" /> Wickets</span><input type="number" id="player-wkts-input" min="0" /></div>
                    </div>
                    <button type="button" class="au-btn au-btn-primary" id="btn-add-manual-player">Add this card</button>
                  </div>
                </div>
              </div>
              <div id="manual-players-hidden" hidden></div>
            </div>
          </section>

          <section class="au-step-panel" data-step="6">
            <div class="au-review-simple" id="review-summary">
              <div class="au-review-row"><span><img src="/assets/icons/cricket/trophy.svg" alt="" /> Auction</span><strong id="review-name">—</strong></div>
              <div class="au-review-row"><span><img src="/assets/icons/cricket/cricket.svg" alt="" /> Tournament</span><strong id="review-tournament-name">{{ $selectedTournament?->name ?? 'Standalone' }}</strong></div>
              <div class="au-review-row"><span><img src="/assets/icons/cricket/bat.svg" alt="" /> Registration</span><strong id="review-registration-mode">Form + Manual</strong></div>
              <div class="au-review-row"><span><img src="/assets/icons/cricket/age.svg" alt="" /> Teams</span><strong id="review-team-count">0</strong></div>
              <div class="au-review-row"><span><img src="/assets/icons/cricket/cricket.svg" alt="" /> Players ready</span><strong id="review-player-count">0</strong></div>
              <div class="au-review-row"><span><img src="/assets/icons/cricket/trophy.svg" alt="" /> Starting purse</span><strong id="review-purse">₹50,00,000</strong></div>
              <div class="au-review-row"><span><img src="/assets/icons/cricket/strike.svg" alt="" /> Base / increment</span><strong id="review-pricing">₹20,000 / ₹10,000</strong></div>
              <div class="au-review-row"><span><img src="/assets/icons/cricket/ball.svg" alt="" /> Timer</span><strong id="review-timer">30s</strong></div>
              <div class="au-review-row"><span><img src="/assets/icons/cricket/cricket.svg" alt="" /> Squad</span><strong id="review-squad">11–25</strong></div>
            </div>
            <div class="au-review-lists">
              <div>
                <h4>Teams</h4>
                <ul id="review-teams-list" class="au-review-ul"><li class="au-muted">None yet</li></ul>
              </div>
              <div>
                <h4>Manual players</h4>
                <ul id="review-players-list" class="au-review-ul"><li class="au-muted">None yet</li></ul>
              </div>
            </div>
            <button type="submit" class="au-btn au-btn-accent au-btn-block" style="margin-top:18px">Create & open workspace</button>
          </section>

          <div class="au-wizard-footer">
            <button type="button" class="au-btn au-btn-ghost" id="btn-prev-step">Back</button>
            <button type="button" class="au-btn au-btn-primary" id="btn-next-step">Next</button>
          </div>
        </div>
      </form>

  <div class="au-modal" id="team-details-modal" hidden>
    <div class="au-modal-card" role="dialog" aria-modal="true" aria-labelledby="team-details-title">
      <div class="au-modal-kicker">New franchise</div>
      <h3 id="team-details-title">Team details</h3>
      <p class="au-muted">Add the owner and home location for <strong id="team-modal-name">this team</strong>.</p>
      <div class="au-form-field">
        <span>Owner name</span>
        <input type="text" id="team-owner-input" placeholder="e.g. Rahul Sharma" autocomplete="name" />
      </div>
      <div class="au-form-field">
        <span>Location / city</span>
        <input type="text" id="team-city-input" placeholder="e.g. Mumbai" autocomplete="address-level2" />
      </div>
      <div class="au-modal-actions">
        <button type="button" class="au-btn au-btn-ghost" id="team-modal-cancel">Cancel</button>
        <button type="button" class="au-btn au-btn-primary" id="team-modal-confirm">Add team</button>
      </div>
    </div>
  </div>

@include('partials.admin-shell-end')
<script src="/auction-create.js?v=6"></script>
</body>
</html>
