@extends('layouts.product')

@section('title', 'Change history')
@section('heading', 'Change history')

@section('content')
  <h2>Audit trail</h2>
  @forelse ($logs as $log)
    <div class="card">
      <div class="row">
        <div>
          <b>{{ $log->action }} · {{ $log->record_ref }}</b>
          <small>{{ $log->user?->full_name ?? $log->actor_label ?? 'System' }} · {{ ucfirst(str_replace('_', ' ', $log->module)) }}</small>
        </div>
        <small>{{ $log->changed_at->format('M j, Y H:i') }}</small>
      </div>
      @if ($log->reason)
        <p>{{ $log->reason }}</p>
      @endif
      @if ($log->before_value || $log->after_value)
        <small>{{ $log->before_value ?? '—' }} → {{ $log->after_value ?? '—' }}</small>
      @endif
    </div>
  @empty
    <p class="sub">No audit activity found.</p>
  @endforelse
  @include('partials.pager', ['paginator' => $logs])
@endsection
