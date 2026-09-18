@extends('auth.layout')

@section('title', 'Reset password')

@section('content')
  <h1>Forgot password</h1>
  <p class="auth-lead">Enter your email and we’ll send a reset link if an account exists.</p>

  <form method="POST" action="{{ route('password.email') }}" class="auth-form">
    @csrf
    <label>
      Email
      <input type="email" name="email" value="{{ old('email') }}" required autofocus>
      @error('email')<em>{{ $message }}</em>@enderror
    </label>
    <button type="submit" class="auth-submit">Send reset link</button>
  </form>

  <p class="auth-links">
    <a href="{{ route('login') }}">Back to sign in</a>
  </p>
@endsection
