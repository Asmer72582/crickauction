@extends('auth.layout')

@section('title', 'Sign in')

@section('content')
  <h1>Sign in</h1>
  <p class="auth-lead">Use your operator account to open the control desk.</p>

  @if (!empty($needsFirstOperator))
    <p class="auth-note">No operator exists yet. <a href="{{ route('register') }}">Create the first account</a>.</p>
  @endif

  <form method="POST" action="{{ route('login') }}" class="auth-form">
    @csrf
    <label>
      Email
      <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
      @error('email')<em>{{ $message }}</em>@enderror
    </label>
    <label>
      Password
      <input type="password" name="password" required autocomplete="current-password">
      @error('password')<em>{{ $message }}</em>@enderror
    </label>
    <label class="auth-check">
      <input type="checkbox" name="remember" value="1">
      Remember me
    </label>
    <button type="submit" class="auth-submit">Sign in</button>
  </form>

  <p class="auth-links">
    <a href="{{ route('password.request') }}">Forgot password?</a>
    @if (!empty($needsFirstOperator))
      <a href="{{ route('register') }}">Create account</a>
    @endif
  </p>
@endsection
