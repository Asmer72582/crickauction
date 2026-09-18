@php
  $icon = $icon ?? 'default';
@endphp
<span class="au-field-icon" aria-hidden="true">
  @switch($icon)
    @case('user')
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>
      @break
    @case('photo')
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="12" cy="11" r="3"/><path d="M8 5l2-2h4l2 2"/></svg>
      @break
    @case('calendar')
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
      @break
    @case('phone')
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="2" width="12" height="20" rx="2"/><path d="M10 18h4"/></svg>
      @break
    @case('email')
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
      @break
    @case('location')
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-5.5 7-11a7 7 0 10-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
      @break
    @case('cricket')
      <img src="/assets/icons/cricket/cricket.svg" alt="" width="18" height="18" />
      @break
    @case('bat')
      <img src="/assets/icons/cricket/bat.svg" alt="" width="18" height="18" />
      @break
    @case('ball')
      <img src="/assets/icons/cricket/ball.svg" alt="" width="18" height="18" />
      @break
    @case('trophy')
      <img src="/assets/icons/cricket/trophy.svg" alt="" width="18" height="18" />
      @break
    @case('strike')
      <img src="/assets/icons/cricket/strike.svg" alt="" width="18" height="18" />
      @break
    @case('age')
      <img src="/assets/icons/cricket/age.svg" alt="" width="18" height="18" />
      @break
    @case('stats')
      <img src="/assets/icons/cricket/strike.svg" alt="" width="18" height="18" />
      @break
    @case('rupee')
      <img src="/assets/icons/cricket/trophy.svg" alt="" width="18" height="18" />
      @break
    @default
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>
  @endswitch
</span>
