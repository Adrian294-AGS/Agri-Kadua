@extends('layouts.product')

@section('title', 'Crop rates')
@section('heading', 'Crop rates')

@section('content')
  <h2>Current purchase rates</h2>
  @forelse ($rates as $rate)
    <div class="card">
      <div class="row">
        <div>
          <b>{{ $rate->variety->crop->crop_name }} · {{ $rate->variety->variety_name }}</b>
          <small>Effective {{ $rate->effective_from->format('M j, Y') }}</small>
        </div>
        <div class="amt">PHP {{ number_format((float) $rate->rate_per_kg, 2) }} / kg</div>
      </div>
    </div>
  @empty
    <p class="sub">No crop rates are currently configured.</p>
  @endforelse
@endsection
