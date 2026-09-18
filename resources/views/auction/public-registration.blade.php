<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $form->auction?->name ?? $form->name }}</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/auction.css?v=41">
</head>
<body class="au-public-body au-public-light">
  <div class="au-public-shell au-public-shell-wide">
    <header class="au-public-hero">
      <div class="au-public-hero-icon" aria-hidden="true">
        <svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="20" stroke="currentColor" stroke-width="2"/><path d="M10 34l14-20 14 20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="34" cy="14" r="3" fill="currentColor"/></svg>
      </div>
      <span class="au-public-badge">Player Registration</span>
      <h1>{{ $form->auction?->name ?? $form->name }}</h1>
      @if ($open)
        <p>Build your player card, then submit. Fields marked <strong>*</strong> are required.</p>
      @endif
    </header>

    @if (!$open)
      <div class="au-closed-card">
        <div class="au-closed-icon">🔒</div>
        <p>Registration is currently closed.<br>Please check back later or contact the organizer.</p>
      </div>
    @else
      <form method="post" action="{{ route('registration.submit', $form->public_token) }}" enctype="multipart/form-data" class="au-public-form au-public-form-card" id="registration-form">
        @csrf
        @php
          $fieldIcons = [
            'full_name' => 'user',
            'profile_photo' => 'photo',
            'date_of_birth' => 'calendar',
            'mobile' => 'phone',
            'whatsapp' => 'phone',
            'email' => 'email',
            'city' => 'location',
            'state' => 'location',
            'playing_role' => 'cricket',
            'batting_style' => 'cricket',
            'bowling_style' => 'cricket',
            'experience' => 'cricket',
            'previous_teams' => 'cricket',
            'requested_base_price' => 'rupee',
            'player_id_doc' => 'doc', 'age_proof' => 'doc',
          ];
          $sectionIndex = 0;
        @endphp

        @include('auction.partials.player-preview-card')

        <div class="au-reg-modal" id="reg-editor-modal" hidden role="dialog" aria-modal="true" aria-labelledby="reg-editor-title">
          <button type="button" class="au-reg-modal-backdrop" id="reg-editor-backdrop" aria-label="Close editor"></button>
          <div class="au-reg-modal-panel">
            <header class="au-reg-modal-head">
              <div>
                <p class="au-reg-modal-kicker">Edit details</p>
                <h2 id="reg-editor-title">Player registration</h2>
              </div>
              <button type="button" class="au-reg-modal-close" id="close-reg-editor" aria-label="Close">×</button>
            </header>
            <div class="au-reg-modal-body" id="reg-editor-body">
              @foreach ($schema['sections'] ?? [] as $section)
                @php
                  $sectionIndex++;
                  $sectionId = $section['id'] ?? '';
                  $isPersonal = $sectionId === 'personal' || str_contains(strtolower($section['title'] ?? ''), 'personal');
                  $isCricket = $sectionId === 'cricket' || str_contains(strtolower($section['title'] ?? ''), 'cricket');
                  $isStats = $sectionId === 'statistics' || str_contains(strtolower($section['title'] ?? ''), 'stat');
                @endphp
                <fieldset class="au-public-section @if($isPersonal) au-section-personal @endif @if($isCricket) au-section-cricket @endif @if($isStats) au-section-stats @endif">
                  <legend>
                    <span class="au-section-step">{{ $sectionIndex }}</span>
                    @if ($isPersonal)
                      @include('auction.partials.field-icon', ['icon' => 'user'])
                    @elseif ($isCricket)
                      @include('auction.partials.field-icon', ['icon' => 'cricket'])
                    @elseif ($isStats)
                      @include('auction.partials.field-icon', ['icon' => 'stats'])
                    @else
                      @include('auction.partials.field-icon', ['icon' => 'doc'])
                    @endif
                    {{ $section['title'] ?? 'Details' }}
                  </legend>
                  <div class="au-section-body">
                    @if ($isStats)
                      @include('auction.partials.stats-section')
                    @else
                      @foreach ($section['fields'] ?? [] as $field)
                        @if (($field['visible'] ?? true) === false) @continue @endif
                        @php $id = $field['id']; $type = $field['type']; @endphp

                        @if ($id === 'playing_role')
                          @include('auction.partials.playing-role-picker')
                          @continue
                        @endif

                        @if ($id === 'wicketkeeper')
                          @continue
                        @endif

                        <label class="au-field au-field-{{ $type }} @if(in_array($id, ['batting_style','bowling_style','city','state','whatsapp','email'])) au-field-half @endif">
                          <span class="au-field-label-row">
                            @include('auction.partials.field-icon', ['icon' => $fieldIcons[$id] ?? 'default'])
                            <span>{{ $field['label'] }}{{ ($field['required'] ?? false) ? ' *' : '' }}</span>
                          </span>
                          @if ($type === 'long_text')
                            <textarea name="{{ $id }}" @required($field['required'] ?? false) placeholder="{{ $field['placeholder'] ?? '' }}">{{ old($id) }}</textarea>
                          @elseif ($type === 'dropdown')
                            <select name="{{ $id }}" @required($field['required'] ?? false)>
                              <option value="">Choose...</option>
                              @foreach ($field['options'] ?? [] as $opt)
                                <option value="{{ $opt }}" @selected(old($id) === $opt)>{{ $opt }}</option>
                              @endforeach
                            </select>
                          @elseif (in_array($type, ['image','file']))
                            <div class="au-file-wrap">
                              <input type="file" name="{{ $id }}" id="file-{{ $id }}" class="au-file-input" @required($field['required'] ?? false) accept="{{ $type === 'image' ? 'image/*' : '' }}" data-preview="{{ $type === 'image' ? 'profile' : '' }}" />
                              <label for="file-{{ $id }}" class="au-file-btn">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                                Choose file
                              </label>
                              <span class="au-file-name" id="file-name-{{ $id }}">No file chosen</span>
                            </div>
                          @elseif ($type === 'checkbox')
                            <label class="au-inline-check"><input type="checkbox" name="{{ $id }}" value="1" /> Yes</label>
                          @else
                            <input type="{{ in_array($type, ['email','phone','number','date']) ? ($type === 'phone' ? 'tel' : $type) : 'text' }}"
                              name="{{ $id }}" value="{{ old($id) }}" @required($field['required'] ?? false)
                              placeholder="{{ $field['placeholder'] ?? '' }}" inputmode="{{ $type === 'phone' ? 'tel' : 'text' }}"
                              @if($id === 'full_name') id="field-full_name" @endif />
                          @endif
                          @if (!empty($field['help']))<em class="au-help">{{ $field['help'] }}</em>@endif
                          @error($id)<span class="au-error">{{ $message }}</span>@enderror
                        </label>
                      @endforeach
                    @endif
                  </div>
                </fieldset>
              @endforeach
            </div>
            <footer class="au-reg-modal-foot">
              <button type="button" class="au-btn au-btn-primary" id="done-reg-editor">Done — update card</button>
            </footer>
          </div>
        </div>

        <label class="au-terms">
          <input type="checkbox" name="terms" value="1" required />
          <span>I confirm all information provided is accurate and I agree to the tournament terms.</span>
        </label>
        @error('terms')<span class="au-error">{{ $message }}</span>@enderror
        <button type="submit" class="au-submit">Submit Registration</button>
      </form>
    @endif
  </div>
  <script src="/public-registration.js?v=41"></script>
</body>
</html>
