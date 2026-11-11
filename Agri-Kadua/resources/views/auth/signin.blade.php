<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in · Co-opSync</title>
  <link rel="stylesheet" href="{{ asset('css/coopsync-app.css') }}">
</head>
<body>
<section id="login">
  <form class="card login" method="POST" action="{{ route('login.store') }}">
    @csrf
    <h1>🌿 Co-opSync</h1>
    <p class="sub">Sign in to San Miguel Farmers Cooperative</p>

    <label>Choose your role</label>
    <div class="roles">
      @foreach ($roles as $role)
        <label>
          <input type="radio" name="role" value="{{ $role->role_code }}" @checked(old('role') === $role->role_code) required>
          <div><span>{{ \App\Support\Menu::icon($role->role_code) }}</span>{{ $role->role_name }}</div>
        </label>
      @endforeach
    </div>

    <label for="employee_code">Employee ID</label>
    <input id="employee_code" name="employee_code" value="{{ old('employee_code') }}" autocomplete="username" maxlength="20" required autofocus>

    <label for="password">Password</label>
    <input id="password" type="password" name="password" autocomplete="current-password" required>

    <label class="check">
      <input type="checkbox" name="guidelines_acknowledged" value="1" @checked(old('guidelines_acknowledged')) required>
      I acknowledge the system security guidelines.
    </label>

    @if ($errors->any())
      <p class="msg er">{{ $errors->first() }}</p>
    @endif

    <button class="btn" type="submit">Sign in</button>
  </form>
</section>
</body>
</html>