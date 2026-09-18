<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Account — Cricket Overlay</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/dashboard.css">
  <link rel="stylesheet" href="/app-shell.css">
  <link rel="stylesheet" href="/auth.css">
</head>
<body class="dash-body">
@php
  $navSection = 'account';
  $pageTitle = 'Account';
  $pageSub = 'Manage your operator profile, password, and additional operators.';
  $topbarNote = 'Settings';
  $brandSubline = 'Operator Console';
  $sidebarFoot = $user->name;
@endphp
@include('partials.admin-shell', compact(
  'navSection',
  'pageTitle',
  'pageSub',
  'topbarNote',
  'brandSubline',
  'sidebarFoot'
))

@if (session('success'))
  <div class="auth-flash">{{ session('success') }}</div>
@endif

<div class="acc-grid">
  <section class="acc-card">
    <h2>Profile</h2>
    <form method="POST" action="{{ route('account.update') }}" class="auth-form">
      @csrf
      @method('PATCH')
      <label>
        Name
        <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
        @error('name')<em>{{ $message }}</em>@enderror
      </label>
      <label>
        Email
        <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
        @error('email')<em>{{ $message }}</em>@enderror
      </label>
      <button type="submit" class="auth-submit">Save profile</button>
    </form>
  </section>

  <section class="acc-card">
    <h2>Change password</h2>
    <form method="POST" action="{{ route('account.password') }}" class="auth-form">
      @csrf
      @method('PATCH')
      <label>
        Current password
        <input type="password" name="current_password" required>
        @error('current_password')<em>{{ $message }}</em>@enderror
      </label>
      <label>
        New password
        <input type="password" name="password" required>
        @error('password')<em>{{ $message }}</em>@enderror
      </label>
      <label>
        Confirm password
        <input type="password" name="password_confirmation" required>
      </label>
      <button type="submit" class="auth-submit">Update password</button>
    </form>
  </section>

  <section class="acc-card acc-card-wide">
    <h2>Operators</h2>
    <ul class="acc-ops">
      @foreach ($operators as $op)
        <li>
          <strong>{{ $op->name }}</strong>
          <span>{{ $op->email }}</span>
        </li>
      @endforeach
    </ul>
    <form method="POST" action="{{ route('account.operators') }}" class="auth-form">
      @csrf
      <label>
        Name
        <input type="text" name="name" value="{{ old('name') }}" required>
      </label>
      <label>
        Email
        <input type="email" name="email" value="{{ old('email') }}" required>
        @error('email')<em>{{ $message }}</em>@enderror
      </label>
      <label>
        Password
        <input type="password" name="password" required>
        @error('password')<em>{{ $message }}</em>@enderror
      </label>
      <label>
        Confirm password
        <input type="password" name="password_confirmation" required>
      </label>
      <button type="submit" class="auth-submit">Add operator</button>
    </form>
  </section>
</div>

@include('partials.admin-shell-end')
