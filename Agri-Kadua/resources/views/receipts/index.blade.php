@extends('layouts.product')

@section('title', 'Receipts')
@section('heading', 'Receipts')

@section('content')
  <div class="row">
    <h2>Delivery receipts</h2>
    @if (auth()->user()->hasRole(\App\Models\Role::WAREHOUSE_ASSISTANT))
      <a class="btn sm" href="{{ route('receive.create') }}">Receive harvest</a>
    @endif
  </div>

  <form method="GET" action="{{ route('receipts.index') }}" class="filters">
    <select name="status" aria-label="Receipt status">
      <option value="">All statuses</option>
      @foreach (['completed', 'stored', 'voided'] as $value)
        <option value="{{ $value }}" @selected($status === $value)>{{ ucfirst($value) }}</option>
      @endforeach
    </select>
    <button class="btn sm" type="submit">Filter</button>
  </form>

  @forelse ($receipts as $receipt)
    <div class="card">
      <div class="row">
        <div>
          <b>{{ $receipt->receipt_code }} · {{ $receipt->member->full_name }}</b>
          <small>{{ $receipt->variety->variety_name }} · {{ $receipt->delivery_date->format('M j, Y') }} · {{ number_format((float) $receipt->net_weight_kg, 2) }} kg accepted</small>
        </div>
        <span class="tag">{{ ucfirst($receipt->status) }}</span>
      </div>
      @if ($receipt->payment)
        <small>Payment: {{ ucfirst(str_replace('_', ' ', $receipt->payment->status)) }} · PHP {{ number_format((float) $receipt->payment->final_amount, 2) }}</small>
      @endif
      @if ($receipt->status === \App\Models\DeliveryReceipt::STATUS_COMPLETED && auth()->user()->hasRole(\App\Models\Role::WAREHOUSE_ASSISTANT))
        <form method="POST" action="{{ route('receipts.approve-storage', $receipt) }}" class="filters">
          @csrf
          <select name="location_id" required aria-label="Storage location">
            <option value="">Choose storage location</option>
            @foreach ($locations as $location)
              <option value="{{ $location->location_id }}">{{ $location->location_name }}</option>
            @endforeach
          </select>
          <button class="btn sm" type="submit">Approve storage</button>
        </form>
      @endif
    </div>
  @empty
    <p class="sub">No receipts found.</p>
  @endforelse

  @include('partials.pager', ['paginator' => $receipts])
@endsection
