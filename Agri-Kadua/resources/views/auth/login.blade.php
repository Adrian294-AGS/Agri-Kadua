<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · Co-opSync</title>
    <link rel="stylesheet" href="{{ asset('css/coopsync.css') }}">
</head>
<body class="login-body">
<div class="login-shell">
    <section class="login-brand">
        <div class="login-brand-inner">
            <div class="brand brand-light">
                <span class="brand-mark">C</span>
                <div class="brand-name">Co-opSync</div>
            </div>
            <h2>San Miguel Farmers Cooperative</h2>
            <p>Real-time cooperative operations. A live internet connection is required; no offline mode.</p>
        </div>
    </section>

    <section class="login-form-wrap">
        <form method="POST" action="{{ route('login.store') }}" class="login-form" novalidate>
            @csrf
            <h1>Authorized Access</h1>
            <p class="muted">Select your cooperative role and enter your secure credentials.</p>

            <div class="field-label">Cooperative role</div>
            <div class="role-grid">
                @foreach ($roles as $role)
                    <label class="role-tile">
                        <input type="radio" name="role" value="{{ $role->role_code }}" @checked(old('role', $roles->first()->role_code) === $role->role_code)>
                        <span>{{ $role->role_name }}</span>
                    </label>
                @endforeach
            </div>

            <label class="field-label" for="employee_code">Employee ID *</label>
            <input id="employee_code" name="employee_code" type="text" class="input" value="{{ old('employee_code') }}" placeholder="WA-0042" autocomplete="username" autofocus>
            <p class="hint">Example: WA-0042 / Jose Mendoza (Warehouse Assistant)</p>

            <label class="field-label" for="password">Password *</label>
            <input id="password" name="password" type="password" class="input" autocomplete="current-password">

            <div class="alert alert-notice">
                <strong>Acknowledge System Security Guidelines</strong><br>
                By signing in, you verify that you are on active warehouse or field duty. All grain receiving entries and quality deduction metrics are logged directly under your employee digital signature.
            </div>

            @if ($errors->any())
                <div class="alert alert-error">
                    <strong>Sign-in problem</strong>
                    @foreach ($errors->unique() as $message)
                        <div>{{ $message }}</div>
                    @endforeach
                </div>
            @endif

            <div id="connection-banner" class="alert alert-warn" hidden>
                <strong>Connection lost.</strong> Submissions are disabled until reconnected. No offline saving.
            </div>

            <button type="submit" class="btn btn-primary btn-block" data-requires-online>Sign in</button>
        </form>
    </section>
</div>
<script src="{{ asset('js/coopsync.js') }}"></script>
</body>
</html>