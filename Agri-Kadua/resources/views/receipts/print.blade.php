@extends('layouts.product')

@section('title', 'Print receipt')
@section('heading', 'Print receipt')

@section('content')
  <div class="row receipt-actions">
    <a class="btn sm o" href="{{ route('receipts.index') }}">Back to receipts</a>
    <button class="btn sm" type="button" onclick="window.print()">Print</button>
  </div>

  <article class="card printable-receipt">
    <header class="receipt-header">
      <div>
        <h2><img class="receipt-logo" src="{{ asset('images/kadua-logo.png') }}" alt="Kadua logo"> Agri-Kadua</h2>
        <p>Harvest payment receipt</p>
      </div>
      <div class="receipt-code">
        <strong>{{ $receipt->payment->slip->slip_code }}</strong>
        <small>{{ $receipt->payment->slip->barcode_value }}</small>
      </div>
    </header>

    <dl class="receipt-details">
      <div><dt>Delivery receipt</dt><dd>{{ $receipt->receipt_code }}</dd></div>
      <div><dt>Payment reference</dt><dd>{{ $receipt->payment->payment_code }}</dd></div>
      <div><dt>Farmer</dt><dd>{{ $receipt->member->full_name }} ({{ $receipt->member->member_code }})</dd></div>
      <div><dt>Crop variety</dt><dd>{{ $receipt->variety->crop->crop_name }} · {{ $receipt->variety->variety_name }}</dd></div>
      <div><dt>Delivery date</dt><dd>{{ $receipt->delivery_date->format('M j, Y') }}</dd></div>
      <div><dt>Payment date</dt><dd>{{ $receipt->payment->paid_at?->format('M j, Y g:i A') ?? '—' }}</dd></div>
    </dl>

    <table>
      <tbody>
        <tr><th>Accepted weight</th><td>{{ number_format((float) $receipt->payment->net_weight_kg, 2) }} kg</td></tr>
        <tr><th>Rate per kilogram</th><td>PHP {{ number_format((float) $receipt->payment->base_rate_per_kg, 2) }}</td></tr>
        <tr><th>Gross value</th><td>PHP {{ number_format((float) $receipt->payment->gross_value, 2) }}</td></tr>
        <tr><th>Input advance</th><td>PHP {{ number_format((float) $receipt->payment->input_advance, 2) }}</td></tr>
        <tr><th>Service fee</th><td>PHP {{ number_format((float) $receipt->payment->service_fee, 2) }}</td></tr>
        <tr><th>Other deductions</th><td>PHP {{ number_format((float) $receipt->payment->standard_deductions, 2) }}</td></tr>
        <tr class="total"><th>Final amount paid</th><td>PHP {{ number_format((float) $receipt->payment->final_amount, 2) }}</td></tr>
      </tbody>
    </table>

    <p><strong>Payment channel:</strong> {{ $receipt->payment->channel?->channel_name ?? '—' }}</p>
    <p><strong>Disbursement reference:</strong> {{ $receipt->payment->bank_reference ?? '—' }}</p>

    <footer class="receipt-signatures">
      <span>Issued by<br><strong>{{ $receipt->payment->slip->issuer->full_name }}</strong></span>
      <span>Member signature<br>________________________</span>
    </footer>
  </article>
@endsection

@push('styles')
<style>
  .receipt-actions{justify-content:flex-end;margin-bottom:12px}
  .printable-receipt{padding:28px;max-width:680px;margin:0 auto}
  .receipt-header{display:flex;justify-content:space-between;gap:20px;border-bottom:2px solid var(--g);padding-bottom:16px}
  .receipt-header h2{margin:0}.receipt-header p{margin:4px 0;color:var(--m)}
  .receipt-code{text-align:right}.receipt-code strong,.receipt-code small{display:block}
  .receipt-details{display:grid;grid-template-columns:1fr 1fr;gap:12px 24px;margin:20px 0}
  .receipt-details div{border-bottom:1px solid var(--b);padding-bottom:6px}
  dt{font-size:12px;color:var(--m)}dd{margin:2px 0 0;font-weight:600}
  table{width:100%;border-collapse:collapse;margin:20px 0}
  th,td{padding:8px 0;border-bottom:1px solid var(--b);text-align:left}
  td{text-align:right}.total th,.total td{border-top:2px solid var(--g);font-size:18px;font-weight:800;padding-top:12px}
  .receipt-signatures{display:flex;justify-content:space-between;gap:24px;margin-top:42px}
  @media print{.receipt-actions{display:none!important}.printable-receipt{border:0;margin:0;max-width:none;padding:0}.receipt-header h2{font-size:24px}}
</style>
@endpush
