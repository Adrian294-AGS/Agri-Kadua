<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · Co-opSync</title>
    <link rel="stylesheet" href="{{ asset('css/coopsync.css') }}">
</head>
<body class="app-body">
@php
    $currentUser = auth()->user();
    $navItems = \App\Support\Navigation::for($currentUser->role->role_code);
    $initials = collect(explode(' ', $currentUser->full_name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('');
@endphp
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">
            <span class="brand-mark">C</span>
            <div>
                <div class="brand-name">Co-opSync</div>
                <div class="brand-sub">{{ $currentUser->branch->branch_name }}</div>
            </div>
        </div>

        <nav class="nav">
            @foreach ($navItems as $item)
                <a href="{{ $item['url'] }}" class="nav-link {{ request()->routeIs($item['route']) ? 'is-active' : '' }}">{{ $item['label'] }}</a>
            @endforeach
        </nav>

        <div class="sidebar-foot">
            <strong>{{ $currentUser->branch->branch_name }}</strong><br>
            Online only
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <div>
                <h1 class="page-title">@yield('heading', 'Dashboard')</h1>
                <p class="page-sub">@yield('subheading')</p>
            </div>
            <div class="topbar-right">
                <span id="live-status" class="pill pill-online">&#9679; Live &middot; Online</span>
                <div class="user-chip">
                    <span class="avatar">{{ $initials }}</span>
                    <span>
                        <span class="user-name">{{ $currentUser->full_name }}</span>
                        <span class="user-role">{{ $currentUser->role->role_name }}</span>
                    </span>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost">Sign out</button>
                </form>
            </div>
        </header>

        <div id="connection-banner" class="alert alert-warn" hidden>
            <strong>Connection lost.</strong> Submissions are disabled until reconnected. No offline saving.
        </div>

        <main class="content">
            @if (session('status'))
                <div class="alert alert-ok">{{ session('status') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
<script src="{{ asset('js/coopsync.js') }}"></script>
</body>
</html>