<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\MillingRelease;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StockService
{
    public function __construct(private TransmissionService $transmission) {}

    /**
     * Release stock as one atomic inventory transaction.
     *
     * @param  array{stock_id:int, quantity_kg:numeric-string|int|float, reason:string}  $data
     */
    public function release(array $data, User $user, ?int $terminalId = null): MillingRelease
    {
        return DB::transaction(function () use ($data, $user, $terminalId) {
            $stock = StockItem::whereKey($data['stock_id'])
                ->where('branch_id', $user->branch_id)
                ->lockForUpdate()
                ->firstOrFail();
            $quantity = (string) $data['quantity_kg'];

            if (bccomp($quantity, '0', 2) <= 0) {
                throw new InvalidArgumentException('Release quantity must be greater than zero.');
            }

            if (bccomp($quantity, (string) $stock->quantity_kg, 2) > 0) {
                throw new InvalidArgumentException('Release quantity exceeds available stock.');
            }

            $stock->quantity_kg = bcsub((string) $stock->quantity_kg, $quantity, 2);
            $stock->save();

            $release = MillingRelease::create([
                'milling_order_code' => 'ML-'.Str::upper(Str::random(17)),
                'variety_id' => $stock->variety_id,
                'quantity_kg' => $quantity,
                'destination' => null,
                'status' => 'released',
                'released_at' => now(),
                'created_by' => $user->user_id,
            ]);

            StockMovement::create([
                'stock_id' => $stock->stock_id,
                'movement_type' => 'release_out',
                'quantity_kg' => $quantity,
                'release_id' => $release->release_id,
                'reason' => $data['reason'],
                'created_by' => $user->user_id,
            ]);

            AuditLog::record(
                module: 'milling_outbound',
                recordRef: $release->milling_order_code,
                action: 'Release Stock',
                reason: $data['reason'],
                before: bcadd((string) $stock->quantity_kg, $quantity, 2).' kg',
                after: $stock->quantity_kg.' kg',
                userId: $user->user_id,
                terminalId: $terminalId,
            );

            $this->transmission->confirm(
                'milling_outbound',
                'milling_release',
                $release->release_id,
                $user->user_id,
                $terminalId,
            );

            return $release->refresh();
        });
    }
}
