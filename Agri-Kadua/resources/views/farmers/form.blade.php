@extends('layouts.product')

@php($editing = isset($member))

@section('title', $editing ? 'Edit Farmer' : 'Add Farmer')
@section('heading', $editing ? 'Edit farmer' : 'Add farmer')

@section('content')
  <p><a href="{{ $editing ? route('farmers.show', $member) : route('farmers.index') }}">&larr; Back to farmers</a></p>

  @if ($errors->any())
    <p class="msg er">{{ $errors->first() }}</p>
  @endif

  <form class="card form-grid" method="POST" action="{{ $editing ? route('farmers.update', $member) : route('farmers.store') }}">
    @csrf
    @if ($editing)
      @method('PUT')
    @endif

    <div>
      <label for="member_code">Member ID</label>
      <input id="member_code" name="member_code" value="{{ old('member_code', $member->member_code ?? '') }}" maxlength="20" required>
    </div>
    <div>
      <label for="full_name">Full name</label>
      <input id="full_name" name="full_name" value="{{ old('full_name', $member->full_name ?? '') }}" maxlength="120" required>
    </div>
    <div>
      <label for="phone">Phone</label>
      <input id="phone" name="phone" value="{{ old('phone', $member->phone ?? '') }}" maxlength="30">
    </div>
    <div>
      <label for="farm_size_ha">Farm size (hectares)</label>
      <input id="farm_size_ha" type="number" name="farm_size_ha" value="{{ old('farm_size_ha', $member->farm_size_ha ?? 0) }}" min="0" step="0.01" required>
    </div>
    <div class="form-grid-wide">
      <label for="land_location">Farm location</label>
      <input id="land_location" name="land_location" value="{{ old('land_location', $member->land_location ?? '') }}" maxlength="255">
    </div>
    <div>
      <label for="soil_classification">Soil classification</label>
      <input id="soil_classification" name="soil_classification" value="{{ old('soil_classification', $member->soil_classification ?? '') }}" maxlength="80">
    </div>
    <div>
      <label for="main_crop_id">Main crop</label>
      <select id="main_crop_id" name="main_crop_id">
        <option value="">Choose a crop</option>
        @foreach ($crops as $crop)
          <option value="{{ $crop->crop_id }}" @selected((string) old('main_crop_id', $member->main_crop_id ?? '') === (string) $crop->crop_id)>{{ $crop->crop_name }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label for="expected_yield_kg">Expected yield (kg)</label>
      <input id="expected_yield_kg" type="number" name="expected_yield_kg" value="{{ old('expected_yield_kg', $member->expected_yield_kg ?? '') }}" min="0" step="0.01">
    </div>
    <div>
      <label for="expected_yield_season">Yield season</label>
      <input id="expected_yield_season" name="expected_yield_season" value="{{ old('expected_yield_season', $member->expected_yield_season ?? '') }}" maxlength="30">
    </div>
    <div>
      <label for="coordinator_id">Field coordinator</label>
      <select id="coordinator_id" name="coordinator_id">
        <option value="">Unassigned</option>
        @foreach ($coordinators as $coordinator)
          <option value="{{ $coordinator->user_id }}" @selected((string) old('coordinator_id', $member->coordinator_id ?? '') === (string) $coordinator->user_id)>{{ $coordinator->full_name }}</option>
        @endforeach
      </select>
    </div>
    @if (auth()->user()->hasRole('cooperative_manager'))
      <div>
        <label for="status">Status</label>
        <select id="status" name="status" required>
          @foreach (['active', 'inactive', 'suspended'] as $status)
            <option value="{{ $status }}" @selected(old('status', $member->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
          @endforeach
        </select>
      </div>
    @else
      <input type="hidden" name="status" value="{{ $member->status ?? 'active' }}">
    @endif

    <div class="form-actions form-grid-wide">
      <button class="btn btn-primary" type="submit">{{ $editing ? 'Save farmer changes' : 'Add farmer' }}</button>
    </div>
  </form>
@endsection
