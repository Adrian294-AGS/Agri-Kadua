<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginEvent;
use App\Models\Role;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login', [
            'roles' => Role::orderBy('role_id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string'],
            'employee_code' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
        ], [
            'employee_code.required' => 'Employee ID and password are required. Sign-in is blocked.',
            'password.required' => 'Employee ID and password are required. Sign-in is blocked.',
            'role.required' => 'Select your cooperative role.',
        ]);

        $employeeCode = strtoupper(trim($data['employee_code']));
        $role = Role::where('role_code', $data['role'])->first();
        $terminal = Terminal::where('is_active', true)->first();

        $success = Auth::attempt([
            'employee_code' => $employeeCode,
            'password' => $data['password'],
            'is_active' => true,
        ]);

        if ($success && Auth::user()->role->role_code !== $data['role']) {
            Auth::logout();
            $success = false;
        }

        LoginEvent::create([
            'user_id' => User::where('employee_code', $employeeCode)->value('user_id'),
            'employee_code_entered' => $employeeCode,
            'selected_role_id' => $role?->role_id,
            'terminal_id' => $terminal?->terminal_id,
            'guidelines_acknowledged' => true,
            'success' => $success,
            'ip_address' => $request->ip(),
        ]);

        if (! $success) {
            throw ValidationException::withMessages([
                'employee_code' => 'Invalid Employee ID, password, or role. Please check your details and try again.',
            ]);
        }

        $request->session()->regenerate();

        Auth::user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}