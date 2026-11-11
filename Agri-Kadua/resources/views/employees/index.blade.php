@extends('layouts.product')

@section('title', 'Employees')
@section('heading', 'Employees')

@section('content')
  <h2>Employee accounts · {{ $branch->branch_name }}</h2>
  <p class="sub">Create employee accounts for this branch. Assign an operational role and share the initial password securely.</p>

  @if ($errors->any())
    <p class="msg er">{{ $errors->first() }}</p>
  @endif

  <form class="card" method="POST" action="{{ route('employees.store') }}">
    @csrf

    <label for="employee_code">Employee ID</label>
    <input id="employee_code" name="employee_code" value="{{ old('employee_code') }}" maxlength="20" autocomplete="off" required>

    <label for="full_name">Full name</label>
    <input id="full_name" name="full_name" value="{{ old('full_name') }}" maxlength="120" autocomplete="name" required>

    <label for="job_title">Job title (optional)</label>
    <input id="job_title" name="job_title" value="{{ old('job_title') }}" maxlength="80">

    <label for="email">Email (optional)</label>
    <input id="email" type="email" name="email" value="{{ old('email') }}" maxlength="150" autocomplete="email">

    <label for="phone">Phone (optional)</label>
    <input id="phone" name="phone" value="{{ old('phone') }}" maxlength="30" autocomplete="tel">

    <label for="role_id">Employee role</label>
    <select id="role_id" name="role_id" required>
      <option value="">Choose a role</option>
      @foreach ($roles as $role)
        <option value="{{ $role->role_id }}" @selected((string) old('role_id') === (string) $role->role_id)>
          {{ $role->role_name }}
        </option>
      @endforeach
    </select>

    <label for="password">Initial password (at least 12 characters)</label>
    <input id="password" type="password" name="password" minlength="12" maxlength="128" autocomplete="new-password" required>

    <label for="password_confirmation">Confirm initial password</label>
    <input id="password_confirmation" type="password" name="password_confirmation" minlength="12" maxlength="128" autocomplete="new-password" required>

    <button class="btn" type="submit">Create employee account</button>
  </form>

  <h2>Employees in this branch</h2>
  @forelse ($employees as $employee)
    <div class="card">
      <div class="row">
        <div>
          <b>{{ $employee->full_name }}</b>
          <small>{{ $employee->employee_code }} · {{ $employee->job_title ?? $employee->role->role_name }}</small>
        </div>
        <span class="tag {{ $employee->is_active ? '' : 'wa' }}">
          {{ $employee->is_active ? $employee->role->role_name : 'Inactive' }}
        </span>
      </div>
    </div>
  @empty
    <p class="sub">No employee accounts found.</p>
  @endforelse

  @include('partials.pager', ['paginator' => $employees])
@endsection
