<?php

namespace App\Http\Controllers;

use App\Models\FarmerMember;
use App\Models\AuditLog;
use App\Models\Crop;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FarmerController extends Controller
{
    private const STATUSES = ['active', 'inactive', 'suspended'];

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $members = FarmerMember::query()
            ->with('crop')
            ->where('branch_id', $request->user()->branch_id)
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . addcslashes($q, '%_\\') . '%';

                $query->where(function ($inner) use ($like) {
                    $inner->where('full_name', 'like', $like)
                        ->orWhere('member_code', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->when(in_array($status, self::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->orderBy('full_name')
            ->simplePaginate(15)
            ->withQueryString();

        return view('farmers.index', [
            'members' => $members,
            'q' => $q,
            'status' => $status,
            'statuses' => self::STATUSES,
        ]);
    }

    public function create(Request $request): View
    {
        return view('farmers.form', $this->formOptions($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $member = DB::transaction(function () use ($data, $request) {
            $member = FarmerMember::create($data + [
                'branch_id' => $request->user()->branch_id,
                'created_by' => $request->user()->user_id,
                'registered_on' => now()->toDateString(),
            ]);

            AuditLog::record('farmer_members', $member->member_code, 'Create Farmer', 'Farmer member registered', null, $member->full_name, $request->user()->user_id);
            return $member;
        });

        return redirect()->route('farmers.show', $member)->with('status', 'Farmer registered successfully.');
    }

    public function edit(Request $request, FarmerMember $member): View
    {
        $this->authorizeBranch($request, $member);

        return view('farmers.form', $this->formOptions($request) + ['member' => $member]);
    }

    public function update(Request $request, FarmerMember $member): RedirectResponse
    {
        $this->authorizeBranch($request, $member);
        $data = $this->validated($request, $member);
        $before = $member->full_name;

        DB::transaction(function () use ($data, $member, $request, $before) {
            $member->update($data);
            AuditLog::record('farmer_members', $member->member_code, 'Update Farmer', 'Farmer member details updated', $before, $member->full_name, $request->user()->user_id);
        });

        return redirect()->route('farmers.show', $member)->with('status', 'Farmer details updated.');
    }

    public function deactivate(Request $request, FarmerMember $member): RedirectResponse
    {
        $this->authorizeBranch($request, $member);
        abort_unless($request->user()->hasRole('cooperative_manager'), 403);

        $member->update(['status' => 'inactive']);
        AuditLog::record('farmer_members', $member->member_code, 'Deactivate Farmer', 'Farmer member deactivated', 'active', 'inactive', $request->user()->user_id);

        return redirect()->route('farmers.show', $member)->with('status', 'Farmer deactivated. Historical records were preserved.');
    }

    public function show(Request $request, FarmerMember $member): View
    {
        $this->authorizeBranch($request, $member);

        $member->load('crop', 'variety', 'coordinator');

        $receipts = $member->receipts()
            ->with('variety', 'payment')
            ->orderByDesc('delivery_date')
            ->orderByDesc('receipt_id')
            ->limit(10)
            ->get();

        return view('farmers.show', [
            'member' => $member,
            'receipts' => $receipts,
        ]);
    }

    private function authorizeBranch(Request $request, FarmerMember $member): void
    {
        abort_unless((int) $member->branch_id === (int) $request->user()->branch_id, 404);
    }

    /** @return array<string, mixed> */
    private function formOptions(Request $request): array
    {
        return [
            'crops' => Crop::orderBy('crop_name')->get(),
            'coordinators' => User::where('branch_id', $request->user()->branch_id)
                ->whereHas('role', fn ($query) => $query->where('role_code', 'field_coordinator'))
                ->where('is_active', true)
                ->orderBy('full_name')
                ->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?FarmerMember $member = null): array
    {
        $data = $request->validate([
            'member_code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9][A-Za-z0-9-]*$/', Rule::unique('farmer_members', 'member_code')->ignore($member?->member_id, 'member_id')],
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'land_location' => ['nullable', 'string', 'max:255'],
            'farm_size_ha' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'soil_classification' => ['nullable', 'string', 'max:80'],
            'main_crop_id' => ['nullable', 'integer', 'exists:crops,crop_id'],
            'expected_yield_kg' => ['nullable', 'numeric', 'min:0'],
            'expected_yield_season' => ['nullable', 'string', 'max:30'],
            'coordinator_id' => ['nullable', 'integer', Rule::exists('users', 'user_id')->where('branch_id', $request->user()->branch_id)],
            'status' => ['required', Rule::in(self::STATUSES)],
        ], [
            'member_code.required' => 'Enter the farmer member ID.',
            'member_code.regex' => 'The member ID may contain only letters, numbers, and hyphens.',
            'member_code.unique' => 'That member ID is already in use. Enter a different ID.',
            'full_name.required' => 'Enter the farmer’s full name.',
            'farm_size_ha.required' => 'Enter the farm size in hectares.',
            'farm_size_ha.numeric' => 'Farm size must be a number, such as 2.5.',
            'farm_size_ha.min' => 'Farm size cannot be negative.',
            'main_crop_id.exists' => 'Choose a crop from the list.',
            'coordinator_id.exists' => 'Choose an active field coordinator from your branch.',
            'status.required' => 'Choose the farmer account status.',
            'status.in' => 'Choose a valid farmer account status.',
        ]);

        if ($member && ! $request->user()->hasRole('cooperative_manager')) {
            $data['status'] = $member->status;
        }

        return $data;
    }
}