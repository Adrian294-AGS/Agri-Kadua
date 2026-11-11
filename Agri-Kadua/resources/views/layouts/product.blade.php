<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Home') · Co-opSync</title>
  <link rel="stylesheet" href="{{ asset('css/coopsync-app.css') }}">
  <script src="{{ asset('js/coopsync.js') }}" defer></script>
</head>
<body>
@php
  $currentUser = auth()->user();
  $menuItems = \App\Support\Menu::for($currentUser->role->role_code);
@endphp
<div id="app">
  <aside class="side">
    <div class="brand">🌿 <b>Co-opSync</b><small>{{ $currentUser->branch->branch_name }}</small></div>
    <div class="usercard">
      <span>{{ \App\Support\Menu::icon($currentUser->role->role_code) }}</span>
      <div><b>{{ $currentUser->full_name }}</b><small>{{ $currentUser->role->role_name }}</small></div>
    </div>
    <nav class="nav">
      @foreach ($menuItems as $item)
        <a href="{{ $item['url'] }}" class="{{ $item['active'] ? 'on' : '' }}">{{ $item['label'] }}</a>
      @endforeach
    </nav>
  </aside>
  <div class="main">
    <header class="top">
      <span>@yield('heading', 'Home')</span>
      <div>
        <span id="live-status" class="pill pill-online">&#9679; Online</span>
        <form method="POST" action="{{ route('logout') }}" class="inline">
          @csrf
          <button type="submit" class="btn sm o">Log out</button>
        </form>
      </div>
    </header>
    <div id="connection-banner" class="banner" hidden>No internet. Changes cannot be saved right now.</div>
    <main id="pg">
      @if (session('status'))
        <p class="msg">{{ session('status') }}</p>
      @endif
      @yield('content')
    </main>
    <footer class="foot"><span>🌿 Co-opSync · {{ $currentUser->branch->branch_name }}</span></footer>
  </div>
</div>
</body>
</html>