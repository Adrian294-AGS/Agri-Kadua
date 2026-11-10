<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CropVariety;
use App\Models\DeliveryReceipt;
use App\Models\ScheduledDelivery;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReceivingService
{
    public function __construct(private TransmissionService $transmission)
    {
    }

    /**
     * Quality deduction rule (ASSUMPTION - confirm with the cooperative):
     * the excess of moisture and impurities over the variety limits, added
     * together and rounded UP to the next whole percent.
     * Wireframe example: 13.5% vs 12% and 1.2% vs 1% -> 1.7% -> 2%.
     * The Warehouse Assistant may override the result manually.
     */
    public function suggestDeductionPct(float $moisture, float $impurities, float $moistureLimit, float $impurityLimit): float
    {
        $excess = max(0, $moisture - $moistureLimit) + max(0, $impurities - $impurityLimit);

        return (float) ceil(round($excess, 4));
    }

    /**
     * Net weight = gross - quality deduction - rejected quantity.
     *
     * @return array{deduction_kg: float, net_weight_kg: float}
     */
    public function calculateNet(float $gross, float $deductionPct, float $rejectedKg): array
    {
        if ($gross <= 0) {
            throw new InvalidArgumentException('Gross weight is required and must exceed 0 kg.');
        }

        if ($rejectedKg < 0 || $deductionPct < 0 || $deductionPct > 100) {
            throw new InvalidArgumentException('Deduction and rejected quantity must be valid, non-negative values.');
        }

        $deductionKg = round($gross * $deductionPct / 100, 2);
        $net = round($gross - $deductionKg - $rejectedKg, 2);

        if ($net < 0) {
            throw new InvalidArgumentException('Rejected quantity and deductions cannot exceed the gross weight.');
        }

        return ['deduction_kg' => $deductionKg, 'net_weight_kg' => $net];
    }

    /**
     * Records a harvest delivery (FR-02, FR-03). The whole save runs in one
     * transaction so an incomplete record is never stored (NFR-06).
     *
     * @param array{member_id:int, variety_id:int, delivery_date:string, gross_weight_kg:float|int|string, moisture_pct?:float|int|string, impurities_pct?:float|int|string, deduction_pct?:float|int|string|null, rejected_kg?:float|int|string, schedule_id?:int|null, notes?:string|null} $data
     */
    public function record(array $data, User $user, ?int $terminalId = null): DeliveryReceipt
    {
        return DB::transaction(function () use ($data, $user, $terminalId) {
            $variety = CropVariety::findOrFail($data['variety_id']);

            $gross = (float) $data['gross_weight_kg'];
            $moisture = (float) ($data['moisture_pct'] ?? 0);
            $impurities = (float) ($data['impurities_pct'] ?? 0);
            $rejected = (float) ($data['rejected_kg'] ?? 0);

            if ($moisture < 0 || $moisture > 100 || $impurities < 0 || $impurities > 100) {
                throw new InvalidArgumentException('Moisture and impurities must be between 0 and 100 percent.');
            }

            $moistureLimit = (float) $variety->moisture_limit_pct;
            $impurityLimit = (float) $variety->impurity_limit_pct;

            $deductionPct = array_key_exists('deduction_pct', $data) && $data['deduction_pct'] !== null && $data['deduction_pct'] !== ''
                ? (float) $data['deduction_pct']
                : $this->suggestDeductionPct($moisture, $impurities, $moistureLimit, $impurityLimit);

            $calc = $this->calculateNet($gross, $deductionPct, $rejected);

            $receipt = DeliveryReceipt::create([
                'receipt_code' => $this->nextReceiptCode(),
                'schedule_id' => $data['schedule_id'] ?? null,
                'member_id' => $data['member_id'],
                'variety_id' => $variety->variety_id,
                'branch_id' => $user->branch_id,
                'terminal_id' => $terminalId,
                'delivery_date' => $data['delivery_date'],
                'gross_weight_kg' => $gross,
                'moisture_pct' => $moisture,
                'impurities_pct' => $impurities,
                'moisture_limit_pct' => $moistureLimit,
                'impurity_limit_pct' => $impurityLimit,
                'deduction_pct' => $deductionPct,
                'deduction_kg' => $calc['deduction_kg'],
                'rejected_kg' => $rejected,
                'net_weight_kg' => $calc['net_weight_kg'],
                'status' => DeliveryReceipt::STATUS_COMPLETED,
                'verification_status' => 'verified',
                'notes' => $data['notes'] ?? null,
                'received_by' => $user->user_id,
                'completed_at' => now(),
            ]);

            if (! empty($data['schedule_id'])) {
                ScheduledDelivery::whereKey($data['schedule_id'])->update(['status' => 'received']);
            }

            AuditLog::record(
                module: 'warehouse_receiving',
                recordRef: $receipt->receipt_code,
                action: 'Record Delivery',
                reason: 'New delivery recorded',
                before: null,
                after: number_format($calc['net_weight_kg'], 2) . ' kg net',
                userId: $user->user_id,
                terminalId: $terminalId,
            );

            $this->transmission->confirm('warehouse_receiving', 'delivery_receipt', $receipt->receipt_id, $user->user_id, $terminalId);

            return $receipt;
        });
    }

    /**
     * "Approve storage": moves a completed receipt's net weight into stock,
     * writes the stock ledger and audit rows (FR-04). Replaces sp_approve_storage.
     */
    public function approveStorage(DeliveryReceipt $receipt, StorageLocation $location, User $user, ?int $terminalId = null): DeliveryReceipt
    {
        return DB::transaction(function () use ($receipt, $location, $user, $terminalId) {
            $locked = DeliveryReceipt::whereKey($receipt->receipt_id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== DeliveryReceipt::STATUS_COMPLETED) {
                throw new InvalidArgumentException('Receipt must exist and be in completed status.');
            }

            $net = (string) $locked->net_weight_kg;

            $stock = StockItem::where('variety_id', $locked->variety_id)
                ->where('location_id', $location->location_id)
                ->lockForUpdate()
                ->first();

            if ($stock) {
                $stock->quantity_kg = bcadd((string) $stock->quantity_kg, $net, 2);
                $stock->save();
            } else {
                $stock = StockItem::create([
                    'branch_id' => $locked->branch_id,
                    'variety_id' => $locked->variety_id,
                    'location_id' => $location->location_id,
                    'quantity_kg' => $net,
                ]);
            }

            StockMovement::create([
                'stock_id' => $stock->stock_id,
                'movement_type' => 'receipt_in',
                'quantity_kg' => $net,
                'receipt_id' => $locked->receipt_id,
                'reason' => 'Storage approved for ' . $locked->receipt_code,
                'created_by' => $user->user_id,
            ]);

            $locked->update([
                'status' => DeliveryReceipt::STATUS_STORED,
                'location_id' => $location->location_id,
                'approved_by' => $user->user_id,
                'approved_at' => now(),
            ]);

            AuditLog::record(
                module: 'inventory',
                recordRef: $locked->receipt_code,
                action: 'Approve Storage',
                reason: 'Storage approved',
                before: 'completed',
                after: 'stored',
                userId: $user->user_id,
                terminalId: $terminalId,
            );

            $this->transmission->confirm('inventory', 'delivery_receipt', $locked->receipt_id, $user->user_id, $terminalId);

            return $locked->refresh();
        });
    }

    private function nextReceiptCode(): string
    {
        $last = DeliveryReceipt::orderByDesc('receipt_id')->value('receipt_code');
        $next = $last ? ((int) substr($last, 3)) + 1 : 1001;

        return 'RC-' . $next;
    }
}