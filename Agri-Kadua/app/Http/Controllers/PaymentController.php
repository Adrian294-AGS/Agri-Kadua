<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordPaymentRequest;
use App\Models\DeliveryReceipt;
use App\Models\Payment;
use App\Models\PaymentChannel;
use App\Models\Terminal;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class PaymentController extends Controller
{
    private const STATUSES = ['pending', 'ready_to_pay', 'paid', 'failed', 'cancelled'];

    public function __construct(private PaymentService $payments) {}

    public function index(Request $request): JsonResponse|View
    {
        $status = (string) $request->query('status', '');
        $branchId = $request->user()->branch_id;

        $payments = Payment::query()
            ->with([
                'member:member_id,member_code,full_name',
                'variety:variety_id,variety_name',
                'channel:channel_id,channel_name',
            ])
            ->whereHas('member', fn ($query) => $query->where('branch_id', $branchId))
            ->when(in_array($status, self::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->orderByDesc('payment_id')
            ->paginate(15)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json($payments);
        }

        $receipts = DeliveryReceipt::query()
            ->with(['member:member_id,member_code,full_name', 'variety:variety_id,variety_name', 'payment'])
            ->where('branch_id', $branchId)
            ->whereIn('status', [DeliveryReceipt::STATUS_COMPLETED, DeliveryReceipt::STATUS_STORED])
            ->where('verification_status', '!=', 'unverified')
            ->whereDoesntHave('payment', fn ($query) => $query->where('status', 'paid'))
            ->orderByDesc('delivery_date')
            ->paginate(15, ['*'], 'receipts_page')
            ->withQueryString();

        return view('payments.index', [
            'payments' => $payments,
            'receipts' => $receipts,
            'channels' => PaymentChannel::where('is_active', true)->orderBy('channel_name')->get(),
            'status' => $status,
        ]);
    }

    public function store(RecordPaymentRequest $request, DeliveryReceipt $receipt): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        abort_unless((int) $receipt->branch_id === (int) $user->branch_id, 404);

        $terminalId = Terminal::where('branch_id', $user->branch_id)
            ->where('is_active', true)
            ->value('terminal_id');

        try {
            $payment = $this->payments->record($receipt, $request->validated(), $user, $terminalId);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['payment' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json($payment->load('member', 'variety', 'channel', 'slip'), 201);
        }

        return redirect()->route('payments.index')->with('status', 'Payment '.$payment->payment_code.' recorded.');
    }
}
