@php
  $pageTitle = $pageTitle ?? 'Auction Desk';
  $pageSub = $pageSub ?? ($tournament->name ?? null);
  $subnavItems = [];

  $topbarMeta = $topbarMeta ?? array_values(array_filter([
    !empty($tournament) ? $tournament->name : null,
    !empty($theme['name']) ? 'Theme: '.$theme['name'] : null,
  ]));

  $brandSubline = $brandSubline ?? 'Auction Workspace';
  $sidebarFoot = $sidebarFoot ?? (!empty($theme['name']) ? $theme['name'] : 'Arena Hub');
  $navSection = 'auctions';
  $topbarNote = $topbarNote ?? 'Auction';
  $primaryAction = $primaryAction ?? null;
  $secondaryAction = $secondaryAction ?? null;
@endphp
@include('partials.admin-shell', compact(
  'navSection',
  'pageTitle',
  'pageSub',
  'topbarNote',
  'topbarMeta',
  'subnavItems',
  'primaryAction',
  'secondaryAction',
  'brandSubline',
  'sidebarFoot'
))
@if (session('success'))
  <div class="au-flash" style="margin:0 0 16px">{{ session('success') }}</div>
@endif
<div class="au-page-body">
