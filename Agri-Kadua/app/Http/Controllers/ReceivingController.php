<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveStorageRequest;
use App\Http\Requests\StoreReceiptRequest;
use App\Models\CropVariety;
use App\Models\DeliveryReceipt;
use App\Models\FarmerMember;
use App\Models\ScheduledDelivery;
use App\Models\StorageLocation;
use App\Models\Terminal;
use App\Services\ReceivingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class ReceivingController extends Controller
{
    private const STATUSES = ['draft', 'completed', 'stored', 'voided'];

    public function __construct(private ReceivingService $receiving) {}

    public function create(Request $request): View
    {
        $branchId = $request->user()->branch_id;

        return view('receipts.create', [
            'members' => FarmerMember::where('branch_id', $branchId)
                ->where('status', 'active')
                ->orderBy('full_name')
                ->get(['member_id', 'member_code', 'full_name', 'primary_variety_id']),
            'varieties' => CropVariety::with('crop')
                ->where('is_active', true)
                ->orderBy('variety_name')
                ->get(),
            'schedules' => ScheduledDelivery::with('member')
                ->whereHas('member', fn ($query) => $query->where('branch_id', $branchId))
                ->whereNotIn('status', ['received', 'cancelled'])
                ->orderBy('scheduled_date')
                ->get(),
        ]);
    }

    public function index(Request $request): JsonResponse|View
    {
        $status = (string) $request->query('status', '');

        $receipts = DeliveryReceipt::query()
            ->with([
                'member:member_id,member_code,full_name',
                'variety:variety_id,variety_name',
                'payment:payment_id,receipt_id,status,final_amount',
            ])
            ->where('branch_id', $request->user()->branch_id)
            ->when(in_array($status, self::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->orderByDesc('delivery_date')
            ->orderByDesc('receipt_id')
            ->paginate(15)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json($receipts);
        }

        return view('receipts.index', [
            'receipts' => $receipts,
            'status' => $status,
            'locations' => StorageLocation::where('branch_id', $request->user()->branch_id)
                ->where('is_active', true)
                ->orderBy('location_name')
                ->get(),
        ]);
    }

    public function store(StoreReceiptRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        try {
            $receipt = $this->receiving->record($request->validated(), $user, $this->terminalId($request));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['receipt' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json($receipt->load('member', 'variety'), 201);
        }

        return redirect()->route('receipts.index')->with('status', 'Delivery '.$receipt->receipt_code.' recorded.');
    }

    public function approveStorage(ApproveStorageRequest $request, DeliveryReceipt $receipt): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        abort_unless((int) $receipt->branch_id === (int) $user->branch_id, 404);

        $location = StorageLocation::where('location_id', $request->validated('location_id'))
            ->where('branch_id', $user->branch_id)
            ->where('is_active', true)
            ->first();

        if (! $location) {
            throw ValidationException::withMessages([
                'location_id' => 'Choose an active storage location in your branch.',
            ]);
        }

        try {
            $stored = $this->receiving->approveStorage($receipt, $location, $user, $this->terminalId($request));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['receipt' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json($stored->load('member', 'variety', 'location'));
        }

        return redirect()->route('receipts.index')->with('status', 'Receipt '.$stored->receipt_code.' stored.');
    }

    private function terminalId(Request $request): ?int
    {
        return Terminal::where('branch_id', $request->user()->branch_id)
            ->where('is_active', true)
            ->value('terminal_id');
    }
}
