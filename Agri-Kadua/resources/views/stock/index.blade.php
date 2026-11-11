@extends('layouts.product')

@section('title', 'Stock')
@section('heading', 'Stock')

@section('content')
  <h2>Warehouse inventory</h2>
  @forelse ($items as $item)
    <div class="card">
      <div class="row">
        <div>
          <b>{{ $item->variety->crop->crop_name }} · {{ $item->variety->variety_name }}</b>
          <small>{{ $item->location->location_name }}</small>
        </div>
        <span class="tag {{ $item->isLow() ? 'wa' : '' }}">{{ $item->isLow() ? 'Low stock' : 'Adequate' }}</span>
      </div>
      <div class="amt">{{ number_format((float) $item->quantity_kg, 2) }} kg</div>
      <small>Alert threshold: {{ number_format((float) $item->low_stock_threshold_kg, 2) }} kg</small>
    </div>
  @empty
    <p class="sub">No stock has been recorded.</p>
  @endforelse
  @include('partials.pager', ['paginator' => $items])

  @if (auth()->user()->hasRole(\App\Models\Role::WAREHOUSE_ASSISTANT))
    <h2>Release stock</h2>
    @if ($errors->any())
      <p class="msg er">{{ $errors->first() }}</p>
    @endif
    <form class="card" method="POST" action="{{ route('stock.release') }}">
      @csrf
      <label for="stock_id">Stock and storage location</label>
      <select id="stock_id" name="stock_id" required>
        <option value="">Choose stock</option>
        @foreach ($items as $item)
          <option value="{{ $item->stock_id }}" @selected((string) old('stock_id') === (string) $item->stock_id)>
            {{ $item->variety->variety_name }} · {{ $item->location->location_name }} ({{ number_format((float) $item->quantity_kg, 2) }} kg)
          </option>
        @endforeach
      </select>

      <label for="quantity_kg">Quantity (kg)</label>
      <input id="quantity_kg" type="number" step="0.01" min="0.01" name="quantity_kg" value="{{ old('quantity_kg') }}" required>

      <label for="reason">Reason</label>
      <input id="reason" name="reason" maxlength="255" value="{{ old('reason') }}" required>

      <button class="btn" type="submit">Confirm release</button>
    </form>
  @endif
@endsection
