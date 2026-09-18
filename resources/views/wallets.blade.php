<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Wallets — Cricket Overlay</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/dashboard.css">
  <link rel="stylesheet" href="/app-shell.css">
</head>
<body class="dash-body">
@php
  $navSection = 'wallets';
  $pageTitle = 'Wallets';
  $pageSub = 'A dedicated wallets area now exists in navigation so the product flow stays stable as billing or balance features grow.';
  $topbarNote = 'Account';
  $brandSubline = 'Wallet workspace';
  $sidebarFoot = 'Ready for future billing';
@endphp
@include('partials.admin-shell', compact(
  'navSection',
  'pageTitle',
  'pageSub',
  'topbarNote',
  'brandSubline',
  'sidebarFoot'
))

  <section class="dash-empty">
    <h3>Wallets area is ready</h3>
    <p>This section is now part of the fixed app flow. You can plug real balances, subscriptions, or top-ups into it later without changing navigation again.</p>
  </section>

@include('partials.admin-shell-end')
</body>
</html>
