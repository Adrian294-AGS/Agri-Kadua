@extends('layouts.product')

@section('title', 'Pay farmers')
@section('heading', 'Pay farmers')

@section('content')
  <h2>Eligible harvests</h2>
  @forelse ($receipts as $receipt)
    <form class="card" method="POST" action="{{ route('payments.store', $receipt) }}">
      @csrf
      <div class="row">
        <div>
          <b>{{ $receipt->member->full_name }} · {{ $receipt->receipt_code }}</b>
          <small>{{ $receipt->variety->variety_name }} · {{ number_format((float) $receipt->net_weight_kg, 2) }} kg · {{ $receipt->delivery_date->format('M j, Y') }}</small>
        </div>
        <span class="tag">{{ ucfirst($receipt->payment?->status ?? 'not recorded') }}</span>
      </div>

      <label for="channel-{{ $receipt->receipt_id }}">Payment channel</label>
      <select id="channel-{{ $receipt->receipt_id }}" name="channel_id">
        <option value="">Select channel</option>
        @foreach ($channels as $channel)
          <option value="{{ $channel->channel_id }}" @selected((string) old('channel_id') === (string) $channel->channel_id)>{{ $channel->channel_name }}</option>
        @endforeach
      </select>

      <label for="advance-{{ $receipt->receipt_id }}">Input advance (PHP)</label>
      <input id="advance-{{ $receipt->receipt_id }}" type="number" step="0.01" min="0" name="input_advance" value="{{ old('input_advance', $receipt->payment?->input_advance ?? 0) }}">

      <label for="fee-{{ $receipt->receipt_id }}">Service fee (PHP)</label>
      <input id="fee-{{ $receipt->receipt_id }}" type="number" step="0.01" min="0" name="service_fee" value="{{ old('service_fee', $receipt->payment?->service_fee ?? 0) }}">

      <label for="deductions-{{ $receipt->receipt_id }}">Other deductions (PHP)</label>
      <input id="deductions-{{ $receipt->receipt_id }}" type="number" step="0.01" min="0" name="standard_deductions" value="{{ old('standard_deductions', $receipt->payment?->standard_deductions ?? 0) }}">

      <label for="bank-reference-{{ $receipt->receipt_id }}">Disbursement reference</label>
      <input id="bank-reference-{{ $receipt->receipt_id }}" name="bank_reference" maxlength="60" required value="{{ old('bank_reference') }}">

      @if ($receipt->payment)
        <label for="reason-{{ $receipt->receipt_id }}">Reason for changing this payment</label>
        <input id="reason-{{ $receipt->receipt_id }}" name="reason" maxlength="255" required>
      @endif

      <input type="hidden" name="status" value="paid">
      <button class="btn" type="submit">Record payment</button>
    </form>
  @empty
    <p class="sub">No verified receipts are awaiting payment.</p>
  @endforelse

  @include('partials.pager', ['paginator' => $receipts])

  <h2>Payment history</h2>
  @forelse ($payments as $payment)
    <div class="card">
      <div class="row">
        <div>
          <b>{{ $payment->payment_code }} · {{ $payment->member->full_name }}</b>
          <small>{{ $payment->variety->variety_name }} · {{ $payment->net_weight_kg }} kg · {{ $payment->channel?->channel_name ?? 'No channel' }}</small>
        </div>
        <div class="amt">PHP {{ number_format((float) $payment->final_amount, 2) }}</div>
      </div>
    </div>
  @empty
    <p class="sub">No payments recorded.</p>
  @endforelse
  @include('partials.pager', ['paginator' => $payments])
@endsection
