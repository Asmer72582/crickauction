@php
  $roles = $roles ?? [];
@endphp
@if (count($roles))
  <div class="au-role-badges">
    @foreach ($roles as $role)
      @php
        $slug = strtolower(str_replace([' ', '-'], ['_', '_'], $role));
        $icon = match (true) {
          str_contains($slug, 'batter') || str_contains($slug, 'batsman') => 'bat',
          str_contains($slug, 'bowl') => 'ball',
          str_contains($slug, 'all') => 'cricket',
          str_contains($slug, 'wicket') || str_contains($slug, 'keeper') => 'trophy',
          default => 'cricket',
        };
      @endphp
      <span class="au-role-badge">
        <span class="au-role-badge-icon" aria-hidden="true">
          <img src="/assets/icons/cricket/{{ $icon }}.svg" alt="" width="14" height="14" />
        </span>
        {{ $role }}
      </span>
    @endforeach
  </div>
@else
  <span class="au-muted">—</span>
@endif
