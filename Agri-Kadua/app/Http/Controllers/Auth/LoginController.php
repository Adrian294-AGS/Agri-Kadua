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
        return view('auth.signin', [
            'roles' => Role::orderBy('role_id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $data = $request->validate([
                'role' => ['required', 'string'],
                'employee_code' => ['required', 'string', 'max:20'],
                'password' => ['required', 'string'],
                'guidelines_acknowledged' => ['accepted'],
            ], [
                'employee_code.required' => 'Employee ID and password are required. Sign-in is blocked.',
                'password.required' => 'Employee ID and password are required. Sign-in is blocked.',
                'role.required' => 'Select your cooperative role.',
                'guidelines_acknowledged.accepted' => 'Acknowledge the system security guidelines to sign in.',
            ]);
        } catch (ValidationException $exception) {
            $this->recordAttempt($request, success: false);

            throw $exception;
        }

        $employeeCode = strtoupper(trim($data['employee_code']));

        $success = Auth::attempt([
            'employee_code' => $employeeCode,
            'password' => $data['password'],
            'is_active' => true,
        ]);

        if ($success && (
            Auth::user()->role->role_code !== $data['role']
            || ! Auth::user()->branch->is_active
        )) {
            Auth::logout();
            $success = false;
        }

        $this->recordAttempt($request, $employeeCode, $success);

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

    private function recordAttempt(Request $request, ?string $employeeCode = null, bool $success = false): void
    {
        $rawEmployeeCode = $request->input('employee_code');
        $rawRoleCode = $request->input('role');
        $employeeCode ??= strtoupper(substr(trim(is_string($rawEmployeeCode) ? $rawEmployeeCode : ''), 0, 20));
        $roleCode = is_string($rawRoleCode) ? substr($rawRoleCode, 0, 30) : '';
        $candidate = User::where('employee_code', $employeeCode)->first();
        $terminalId = $candidate
            ? Terminal::where('branch_id', $candidate->branch_id)->where('is_active', true)->value('terminal_id')
            : null;

        LoginEvent::create([
            'user_id' => $candidate?->user_id,
            'employee_code_entered' => $employeeCode,
            'selected_role_id' => Role::where('role_code', $roleCode)->value('role_id'),
            'terminal_id' => $terminalId,
            'guidelines_acknowledged' => $request->boolean('guidelines_acknowledged'),
            'success' => $success,
            'ip_address' => $request->ip(),
        ]);
    }
}
