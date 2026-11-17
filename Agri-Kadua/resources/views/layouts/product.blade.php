<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Home') · Agri-Kadua</title>
  <link rel="stylesheet" href="{{ asset('css/coopsync.css') }}">
  @stack('styles')
  <script src="{{ asset('js/coopsync.js') }}" defer></script>
</head>
<body>
<div class="page-loader" aria-hidden="true">
  <div class="skeleton-layout">
    <aside class="skeleton-sidebar">
      <span class="skeleton skeleton-brand"></span>
      <span class="skeleton skeleton-user"></span>
      <span class="skeleton"></span>
      <span class="skeleton"></span>
      <span class="skeleton skeleton-short"></span>
    </aside>
    <section class="skeleton-main">
      <div class="skeleton-topbar">
        <span class="skeleton skeleton-heading"></span>
        <span class="skeleton skeleton-status"></span>
      </div>
      <div class="skeleton-content">
        <span class="skeleton skeleton-page-title"></span>
        <span class="skeleton skeleton-description"></span>
        <div class="skeleton-filters">
          <span class="skeleton"></span>
          <span class="skeleton skeleton-filter"></span>
          <span class="skeleton skeleton-button"></span>
        </div>
        <div class="skeleton-cards">
          <span class="skeleton skeleton-card"></span>
          <span class="skeleton skeleton-card"></span>
          <span class="skeleton skeleton-card"></span>
        </div>
      </div>
    </section>
  </div>
</div>
@php
  $currentUser = auth()->user();
  $menuItems = \App\Support\Menu::for($currentUser->role->role_code);
@endphp
<div id="app" class="page-enter">
  <aside class="side">
    <div class="brand"><img class="brand-logo" src="{{ asset('images/kadua-logo.png') }}" alt="Kadua logo"><div><b>Agri-Kadua</b><small>{{ $currentUser->branch->branch_name }}</small></div></div>
    <div class="usercard">
      <span>{!! \App\Support\Menu::icon($currentUser->role->role_code) !!}</span>
      <div><b>{{ $currentUser->full_name }}</b><small>{{ $currentUser->role->role_name }}</small></div>
    </div>
    <nav class="nav">
      @foreach ($menuItems as $item)
        <a href="{{ $item['url'] }}" class="{{ $item['active'] ? 'on' : '' }}"><span class="nav-icon">{!! \App\Support\Menu::navIcon($item['route']) !!}</span>{{ $item['label'] }}</a>
      @endforeach
    </nav>
  </aside>
  <div class="main">
    <header class="top">
      <span>@yield('heading', 'Home')</span>
      <div>
        @include('partials.notification-bell')
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
    <footer class="foot"><span><img class="footer-logo" src="{{ asset('images/kadua-logo.png') }}" alt=""> Agri-Kadua · {{ $currentUser->branch->branch_name }}</span></footer>
  </div>
</div>
</body>
</html>