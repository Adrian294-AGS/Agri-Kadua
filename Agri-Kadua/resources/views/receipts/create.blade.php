@extends('layouts.product')

@section('title', 'Receive harvest')
@section('heading', 'Receive harvest')

@section('content')
  <p><a href="{{ route('receipts.index') }}">&larr; Receipts</a></p>
  <h2>Record delivery</h2>

  @if ($errors->any())
    <p class="msg er">{{ $errors->first() }}</p>
  @endif

  <form class="card" method="POST" action="{{ route('receipts.store') }}">
    @csrf
    <label for="member_id">Farmer member</label>
    <select id="member_id" name="member_id" required>
      <option value="">Choose farmer</option>
      @foreach ($members as $member)
        <option value="{{ $member->member_id }}" @selected((string) old('member_id') === (string) $member->member_id)>
          {{ $member->full_name }} ({{ $member->member_code }})
        </option>
      @endforeach
    </select>

    <label for="variety_id">Crop variety</label>
    <select id="variety_id" name="variety_id" required>
      <option value="">Choose crop variety</option>
      @foreach ($varieties as $variety)
        <option value="{{ $variety->variety_id }}" @selected((string) old('variety_id') === (string) $variety->variety_id)>
          {{ $variety->crop->crop_name }} · {{ $variety->variety_name }}
        </option>
      @endforeach
    </select>

    <label for="schedule_id">Scheduled delivery (optional)</label>
    <select id="schedule_id" name="schedule_id">
      <option value="">Unscheduled delivery</option>
      @foreach ($schedules as $schedule)
        <option value="{{ $schedule->schedule_id }}" @selected((string) old('schedule_id') === (string) $schedule->schedule_id)>
          {{ $schedule->member->full_name }} · {{ $schedule->scheduled_date->format('M j, Y') }}
        </option>
      @endforeach
    </select>

    <label for="delivery_date">Delivery date</label>
    <input id="delivery_date" type="date" name="delivery_date" value="{{ old('delivery_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>

    <label for="gross_weight_kg">Gross weight (kg)</label>
    <input id="gross_weight_kg" type="number" step="0.01" min="0.01" name="gross_weight_kg" value="{{ old('gross_weight_kg') }}" required>

    <label for="moisture_pct">Moisture (%)</label>
    <input id="moisture_pct" type="number" step="0.01" min="0" max="100" name="moisture_pct" value="{{ old('moisture_pct', 0) }}">

    <label for="impurities_pct">Impurities (%)</label>
    <input id="impurities_pct" type="number" step="0.01" min="0" max="100" name="impurities_pct" value="{{ old('impurities_pct', 0) }}">

    <label for="deduction_pct">Quality deduction (%) (leave blank for suggested deduction)</label>
    <input id="deduction_pct" type="number" step="0.01" min="0" max="100" name="deduction_pct" value="{{ old('deduction_pct') }}">

    <label for="rejected_kg">Rejected weight (kg)</label>
    <input id="rejected_kg" type="number" step="0.01" min="0" name="rejected_kg" value="{{ old('rejected_kg', 0) }}">

    <label for="notes">Notes (optional)</label>
    <textarea id="notes" name="notes" maxlength="255">{{ old('notes') }}</textarea>

    <button class="btn" type="submit">Save harvest receipt</button>
  </form>
@endsection
