<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $employees = User::query()
            ->with('role')
            ->where('branch_id', $request->user()->branch_id)
            ->orderBy('full_name')
            ->paginate(15)
            ->withQueryString();

        $roles = Role::query()
            ->whereIn('role_code', [
                Role::WAREHOUSE_ASSISTANT,
                Role::FIELD_COORDINATOR,
                Role::ACCOUNTING_CLERK,
                Role::COOPERATIVE_MANAGER,
            ])
            ->orderBy('role_name')
            ->get();

        return view('employees.index', [
            'employees' => $employees,
            'roles' => $roles,
            'branch' => Branch::findOrFail($request->user()->branch_id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (is_string($request->input('employee_code'))) {
            $request->merge(['employee_code' => strtoupper(trim($request->input('employee_code')))]);
        }

        if (is_string($request->input('full_name'))) {
            $request->merge(['full_name' => trim($request->input('full_name'))]);
        }

        $data = $request->validate([
            'employee_code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9][A-Za-z0-9-]*$/',
                Rule::unique('users', 'employee_code'),
            ],
            'full_name' => ['required', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'role_id')->whereIn('role_code', [
                    Role::WAREHOUSE_ASSISTANT,
                    Role::FIELD_COORDINATOR,
                    Role::ACCOUNTING_CLERK,
                    Role::COOPERATIVE_MANAGER,
                ]),
            ],
            'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed'],
        ], [
            'employee_code.required' => 'Enter an employee ID.',
            'employee_code.regex' => 'The employee ID may contain only letters, numbers, and hyphens.',
            'employee_code.unique' => 'That employee ID is already in use. Enter a different ID.',
            'full_name.required' => 'Enter the employee’s full name.',
            'email.email' => 'Enter a valid email address, or leave this field blank.',
            'role_id.required' => 'Choose the employee’s role.',
            'role_id.exists' => 'Choose a valid employee role.',
            'password.required' => 'Enter an initial password.',
            'password.min' => 'The initial password must be at least 12 characters.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        $employee = DB::transaction(function () use ($data, $request) {
            $employee = User::create([
                'employee_code' => $data['employee_code'],
                'full_name' => $data['full_name'],
                'job_title' => $data['job_title'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'password_hash' => $data['password'],
                'role_id' => $data['role_id'],
                'branch_id' => $request->user()->branch_id,
                'is_active' => true,
            ]);

            AuditLog::record(
                module: 'user_access',
                recordRef: $employee->employee_code,
                action: 'Create Employee Account',
                reason: 'Employee account created',
                after: $employee->full_name.' ('.$employee->role->role_name.')',
                userId: $request->user()->user_id,
            );

            return $employee;
        });

        return redirect()
            ->route('employees.index')
            ->with('status', 'Account created for '.$employee->full_name.'. Share the Employee ID and initial password with them securely.');
    }

    public function toggleActive(Request $request, User $employee): RedirectResponse
    {
        abort_unless((int) $employee->branch_id === (int) $request->user()->branch_id, 404);
        abort_if((int) $employee->user_id === (int) $request->user()->user_id, 422, 'You cannot deactivate your own account.');

        $employee->update(['is_active' => ! $employee->is_active]);
        $action = $employee->is_active ? 'Reactivate Employee Account' : 'Deactivate Employee Account';
        AuditLog::record('user_access', $employee->employee_code, $action, 'Employee account status changed', null, $employee->is_active ? 'active' : 'inactive', $request->user()->user_id);

        return redirect()->route('employees.index')->with('status', $employee->is_active ? 'Employee account reactivated.' : 'Employee account deactivated.');
    }
}
