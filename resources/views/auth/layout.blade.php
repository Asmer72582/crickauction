<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'Sign in') — Cricket Overlay</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/app-shell.css">
  <link rel="stylesheet" href="/auth.css">
</head>
<body class="auth-body">
  <div class="auth-shell">
    <div class="auth-brand">
      <div class="app-brand-mark">CO</div>
      <div>
        <strong>Cricket Overlay</strong>
        <span>Operator Console</span>
      </div>
    </div>
    <div class="auth-card">
      @if (session('status'))
        <div class="auth-flash">{{ session('status') }}</div>
      @endif
      @yield('content')
    </div>
  </div>
</body>
</html>
