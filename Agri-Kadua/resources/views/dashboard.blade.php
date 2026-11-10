@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', $user->role->role_code === 'cooperative_manager' ? 'Dashboard & Reports' : $user->role->role_name . ' Dashboard')
@section('subheading', 'Welcome back, ' . $user->full_name . '.')

@section('content')
    <div class="stat-grid">
        @foreach ($stats as $stat)
            <div class="card stat">
                <div class="stat-label">{{ $stat['label'] }}</div>
                <div class="stat-value">{{ $stat['value'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <h2 class="card-title">Your workspace</h2>
        <p class="muted">
            You are signed in as <strong>{{ $user->role->role_name }}</strong> at {{ $user->branch->branch_name }}.
            Use the menu on the left to open the functions available to your role.
        </p>
    </div>
@endsection