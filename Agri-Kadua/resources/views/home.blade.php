@extends('layouts.product')

@section('title', 'Home')
@section('heading', 'Home')

@section('content')
  @php
    $actions = collect(\App\Support\Menu::for($user->role->role_code))->where('route', '!=', 'dashboard');
  @endphp

  <h2>Hello, {{ explode(' ', trim($user->full_name))[0] }}</h2>

  <div class="tiles">
    @foreach ($stats as $stat)
      <div class="tile"><small>{{ $stat['label'] }}</small><b>{{ $stat['value'] }}</b></div>
    @endforeach
  </div>

  @if ($actions->isNotEmpty())
    <h3>Quick actions</h3>
    @foreach ($actions as $action)
      <a class="go" href="{{ $action['url'] }}">{{ $action['label'] }}</a>
    @endforeach
  @endif
@endsection