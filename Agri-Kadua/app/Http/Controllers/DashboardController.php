<?php

namespace App\Http\Controllers;

use App\Models\DeliveryReceipt;
use App\Models\FarmerMember;
use App\Models\FieldInputRequest;
use App\Models\Payment;
use App\Models\Role;
use App\Models\ScheduledDelivery;
use App\Models\StockItem;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load('role', 'branch');

        $branchId = $user->branch_id;
        $lowStock = StockItem::where('branch_id', $branchId)
            ->whereColumn('quantity_kg', '<=', 'low_stock_threshold_kg')
            ->count();

        $stats = match ($user->role->role_code) {
            Role::WAREHOUSE_ASSISTANT => [
                ['label' => 'Receipts pending storage', 'value' => number_format(DeliveryReceipt::where('branch_id', $branchId)->where('status', 'completed')->count())],
                ['label' => 'Receipts stored', 'value' => number_format(DeliveryReceipt::where('branch_id', $branchId)->where('status', 'stored')->count())],
                ['label' => 'Low-stock alerts', 'value' => number_format($lowStock)],
            ],
            Role::FIELD_COORDINATOR => [
                ['label' => 'Expected deliveries', 'value' => number_format(ScheduledDelivery::whereHas('member', fn ($query) => $query->where('branch_id', $branchId))->whereNotIn('status', ['received', 'cancelled'])->count())],
                ['label' => 'Open input requests', 'value' => number_format(FieldInputRequest::whereHas('member', fn ($query) => $query->where('branch_id', $branchId))->whereIn('status', ['draft', 'submitted'])->count())],
                ['label' => 'Active members', 'value' => number_format(FarmerMember::where('branch_id', $branchId)->where('status', 'active')->count())],
            ],
            Role::ACCOUNTING_CLERK => [
                ['label' => 'Verified harvests awaiting payment', 'value' => number_format(
                    DeliveryReceipt::where('branch_id', $branchId)->whereIn('status', ['completed', 'stored'])
                        ->where('verification_status', '!=', 'unverified')
                        ->whereDoesntHave('payment', fn ($query) => $query->where('status', 'paid'))
                        ->count()
                )],
                ['label' => 'Pending payments', 'value' => number_format(Payment::whereHas('member', fn ($query) => $query->where('branch_id', $branchId))->where('status', 'pending')->count())],
                ['label' => 'Paid settlements (PHP)', 'value' => number_format((float) Payment::whereHas('member', fn ($query) => $query->where('branch_id', $branchId))->where('status', 'paid')->sum('final_amount'), 2)],
            ],
            default => [
                ['label' => 'Accepted harvest (kg)', 'value' => number_format((float) DeliveryReceipt::where('branch_id', $branchId)->whereIn('status', ['completed', 'stored'])->sum('net_weight_kg'), 2)],
                ['label' => 'Active members', 'value' => FarmerMember::where('branch_id', $branchId)->where('status', 'active')->count().' / '.FarmerMember::where('branch_id', $branchId)->count()],
                ['label' => 'Disbursed settlements (PHP)', 'value' => number_format((float) Payment::whereHas('member', fn ($query) => $query->where('branch_id', $branchId))->where('status', 'paid')->sum('final_amount'), 2)],
                ['label' => 'Low-stock alerts', 'value' => number_format($lowStock)],
            ],
        };

        return view('home', [
            'user' => $user,
            'stats' => $stats,
        ]);
    }
}
