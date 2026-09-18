<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Preview — {{ $form->name }}</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/auction.css">
</head>
<body class="au-public-body au-public-light">
  <div class="au-preview-banner">Preview mode — submissions disabled</div>
  <div class="au-public-shell au-public-shell-wide">
    <header class="au-public-hero">
      <span class="au-public-badge">Preview</span>
      <h1>{{ $form->name }}</h1>
    </header>
    @foreach ($schema['sections'] ?? [] as $section)
      @php
        $isStats = ($section['id'] ?? '') === 'statistics' || str_contains(strtolower($section['title'] ?? ''), 'stat');
      @endphp
      <fieldset class="au-public-section" disabled>
        <legend>{{ $section['title'] ?? 'Details' }}</legend>
        <div class="au-section-body">
          @if ($isStats)
            <div class="au-stats-board" style="opacity:.85;pointer-events:none">
              <div class="au-stats-input-grid">
                @foreach (['Matches','Runs','Highest Score','Wickets'] as $label)
                  <label class="au-stat-input"><span class="au-stat-input-label">{{ $label }}</span><input disabled placeholder="—" /></label>
                @endforeach
              </div>
              <p class="au-stats-note">Stats auto-calculated on submit</p>
            </div>
          @else
            @foreach ($section['fields'] ?? [] as $field)
              @if (($field['visible'] ?? true) === false) @continue @endif
              @if ($field['id'] === 'playing_role')
                <div class="au-field au-field-role-picker" style="opacity:.7;pointer-events:none">
                  <span class="au-field-label-row"><span>Playing Role *</span></span>
                  <div class="au-role-grid">
                    @foreach (['Batter','Bowler','All-rounder','Wicketkeeper'] as $r)
                      <div class="au-role-card"><span class="au-role-name">{{ $r }}</span></div>
                    @endforeach
                  </div>
                </div>
                @continue
              @endif
              @if ($field['id'] === 'wicketkeeper') @continue @endif
              <label class="au-field">
                <span class="au-field-label-row"><span>{{ $field['label'] }}</span></span>
                <input disabled placeholder="..." />
              </label>
            @endforeach
          @endif
        </div>
      </fieldset>
    @endforeach
  </div>
</body>
</html>
