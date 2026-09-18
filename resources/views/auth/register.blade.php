@extends('auth.layout')

@section('title', 'Create operator')

@section('content')
  <h1>Create first operator</h1>
  <p class="auth-lead">This account can run auctions, scoring, and broadcast control.</p>

  <form method="POST" action="{{ route('register') }}" class="auth-form">
    @csrf
    <label>
      Name
      <input type="text" name="name" value="{{ old('name') }}" required autofocus>
      @error('name')<em>{{ $message }}</em>@enderror
    </label>
    <label>
      Email
      <input type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
      @error('email')<em>{{ $message }}</em>@enderror
    </label>
    <label>
      Password
      <input type="password" name="password" required autocomplete="new-password">
      @error('password')<em>{{ $message }}</em>@enderror
    </label>
    <label>
      Confirm password
      <input type="password" name="password_confirmation" required autocomplete="new-password">
    </label>
    <button type="submit" class="auth-submit">Create account</button>
  </form>

  <p class="auth-links">
    <a href="{{ route('login') }}">Back to sign in</a>
  </p>
@endsection
