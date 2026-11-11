@extends('layouts.product')

@section('title', 'Farmers')
@section('heading', 'Farmers')

@section('content')
  <h2>Farmers</h2>

  <form method="GET" action="{{ route('farmers.index') }}" class="filters">
    <input type="search" name="q" value="{{ $q }}" placeholder="Search name, ID or phone" aria-label="Search farmers">
    <select name="status" aria-label="Status">
      <option value="">All status</option>
      @foreach ($statuses as $s)
        <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst($s) }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn sm">Search</button>
  </form>

  @php $tagClass = ['active' => '', 'inactive' => 'wa', 'suspended' => 'er']; @endphp

  @forelse ($members as $member)
    <a class="card" href="{{ route('farmers.show', $member) }}">
      <div class="row">
        <div>
          <b>{{ $member->full_name }}</b>
          <small>
            {{ $member->member_code }} · {{ number_format((float) $member->farm_size_ha, 1) }} ha · {{ $member->phone ?? 'No phone' }}<br>
            {{ $member->land_location ?? 'No location on file' }}
          </small>
        </div>
        <div style="text-align:right">
          <span class="tag">{{ $member->crop?->crop_name ?? 'No crop' }}</span>
          @if ($member->status !== 'active')
            <br><span class="tag {{ $tagClass[$member->status] ?? '' }}">{{ ucfirst($member->status) }}</span>
          @endif
        </div>
      </div>
    </a>
  @empty
    <p class="sub">No farmers match your search.</p>
  @endforelse

  @include('partials.pager', ['paginator' => $members])
@endsection