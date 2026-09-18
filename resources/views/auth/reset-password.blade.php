@extends('auth.layout')

@section('title', 'Set new password')

@section('content')
  <h1>Set a new password</h1>
  <p class="auth-lead">Choose a password with at least 8 characters.</p>

  <form method="POST" action="{{ route('password.update') }}" class="auth-form">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <label>
      Email
      <input type="email" name="email" value="{{ old('email', $email) }}" required>
      @error('email')<em>{{ $message }}</em>@enderror
    </label>
    <label>
      New password
      <input type="password" name="password" required autocomplete="new-password">
      @error('password')<em>{{ $message }}</em>@enderror
    </label>
    <label>
      Confirm password
      <input type="password" name="password_confirmation" required autocomplete="new-password">
    </label>
    <button type="submit" class="auth-submit">Update password</button>
  </form>
@endsection
