@php
  $selected = old('playing_role', []);
  if (is_string($selected)) {
    $selected = array_map('trim', explode(',', $selected));
  }
  $roles = [
    ['id' => 'batter', 'label' => 'Batter', 'icon' => 'bat'],
    ['id' => 'bowler', 'label' => 'Bowler', 'icon' => 'ball'],
    ['id' => 'all_rounder', 'label' => 'All-rounder', 'icon' => 'cricket'],
    ['id' => 'wicketkeeper', 'label' => 'Wicketkeeper', 'icon' => 'trophy'],
  ];
@endphp
<div class="au-field au-field-role-picker" data-max-roles="2">
  <span class="au-field-label-row">
    @include('auction.partials.field-icon', ['icon' => 'cricket'])
    <span>Playing Role * <em class="au-role-hint">(pick up to 2)</em></span>
  </span>
  <div class="au-role-grid" id="playing-role-grid">
    @foreach ($roles as $role)
      <label class="au-role-card {{ in_array($role['label'], $selected) || in_array($role['id'], $selected) ? 'is-selected' : '' }}">
        <input type="checkbox" name="playing_role[]" value="{{ $role['label'] }}"
          {{ in_array($role['label'], $selected) || in_array($role['id'], $selected) ? 'checked' : '' }} />
        <span class="au-role-icon" aria-hidden="true">
          <img class="au-role-icon-img" src="/assets/icons/cricket/{{ $role['icon'] }}.svg" alt="" />
        </span>
        <span class="au-role-name">{{ $role['label'] }}</span>
      </label>
    @endforeach
  </div>
  <p class="au-role-counter"><span id="role-count">0</span> / 2 selected</p>
  @error('playing_role')<span class="au-error">{{ $message }}</span>@enderror
</div>
