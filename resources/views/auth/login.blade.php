<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>JARVIS AI — Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;500&family=Share+Tech+Mono&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/jarvis.css') }}">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">

    <div class="auth-logo">
      <div class="auth-orb">J</div>
      <h1 class="auth-title">JARVIS</h1>
      <p class="auth-subtitle">AI Voice Assistant — Authenticate to Continue</p>
    </div>

    @if ($errors->any())
      <div class="alert-error">
        @foreach ($errors->all() as $error)
          <div>{{ $error }}</div>
        @endforeach
      </div>
    @endif

    <form action="/login" method="POST">
      @csrf

      <div class="form-group">
        <label class="form-label" for="email">Email Address</label>
        <input
          type="email"
          id="email"
          name="email"
          class="form-control"
          value="{{ old('email') }}"
          required
          autocomplete="email"
          placeholder="your@email.com"
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input
          type="password"
          id="password"
          name="password"
          class="form-control"
          required
          autocomplete="current-password"
          placeholder="••••••••"
        >
      </div>

      <button type="submit" class="btn-primary-jarvis">
        INITIALIZE JARVIS
      </button>
    </form>

    <div class="auth-link">
      New here? <a href="/register">Create an account</a>
    </div>

  </div>
</div>
@include('partials.sweetalert')
</body>
</html>
