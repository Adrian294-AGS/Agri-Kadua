@extends('layouts.product')

@section('title', $member->full_name)
@section('heading', 'Farmers')

@section('content')
  @php $tagClass = ['active' => '', 'inactive' => 'wa', 'suspended' => 'er']; @endphp

  <p><a href="{{ route('farmers.index') }}">&larr; All farmers</a></p>
  <h2>{{ $member->full_name }}</h2>

  <div class="card">
    <dl class="kv">
      <dt>Member ID</dt><dd>{{ $member->member_code }}</dd>
      <dt>Status</dt><dd><span class="tag {{ $tagClass[$member->status] ?? '' }}">{{ ucfirst($member->status) }}</span></dd>
      <dt>Phone</dt><dd>{{ $member->phone ?? '—' }}</dd>
      <dt>Location</dt><dd>{{ $member->land_location ?? '—' }}</dd>
      <dt>Farm size</dt><dd>{{ number_format((float) $member->farm_size_ha, 1) }} ha</dd>
      <dt>Soil</dt><dd>{{ $member->soil_classification ?? '—' }}</dd>
      <dt>Main crop</dt><dd>{{ $member->crop?->crop_name ?? '—' }}@if ($member->variety) · {{ $member->variety->variety_name }}@endif</dd>
      <dt>Expected yield</dt>
      <dd>{{ $member->expected_yield_kg !== null ? number_format((float) $member->expected_yield_kg) . ' kg' . ($member->expected_yield_season ? ' (' . $member->expected_yield_season . ')' : '') : '—' }}</dd>
      <dt>Coordinator</dt><dd>{{ $member->coordinator?->full_name ?? '—' }}</dd>
      <dt>Registered</dt><dd>{{ $member->registered_on->format('M j, Y') }}</dd>
    </dl>
  </div>

  <h3>Recent deliveries</h3>
  @forelse ($receipts as $receipt)
    <div class="card">
      <div class="row">
        <div>
          <b>{{ $receipt->receipt_code }} · {{ $receipt->variety->variety_name }}</b>
          <small>{{ $receipt->delivery_date->format('M j, Y') }} · {{ number_format((float) $receipt->net_weight_kg, 2) }} kg accepted</small>
        </div>
        @if ($receipt->payment?->status === 'paid')
          <span class="tag">Paid</span>
        @else
          <span class="tag wa">Not paid</span>
        @endif
      </div>
    </div>
  @empty
    <p class="sub">No deliveries yet.</p>
  @endforelse
@endsection