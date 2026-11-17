<?php

namespace App\Services;

use App\Models\DeliveryReceipt;
use App\Models\MillingRelease;
use App\Models\Payment;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\User;
use App\Notifications\RecordActivity;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends in-app notifications after a business action has already been saved.
 * Recipients are active users of the same branch, never the person who acted.
 * A failure here is reported but never undoes or blocks the saved record.
 */
class NotificationService
{
    public function deliveryRecorded(DeliveryReceipt $receipt, User $actor): void
    {
        $this->guard(function () use ($receipt, $actor) {
            $receipt->loadMissing('member', 'variety');

            $this->send($actor, [Role::ACCOUNTING_CLERK, Role::COOPERATIVE_MANAGER], [
                'title' => 'New delivery '.$receipt->receipt_code,
                'message' => ($receipt->member?->full_name ?? 'A member').' delivered '
                    .number_format((float) $receipt->net_weight_kg, 2).' kg net of '
                    .($receipt->variety?->variety_name ?? 'harvest').'.',
                'module' => 'warehouse_receiving',
                'record_ref' => $receipt->receipt_code,
                'url' => route('receipts.index', [], false),
            ]);
        });
    }

    public function receiptStored(DeliveryReceipt $receipt, User $actor): void
    {
        $this->guard(function () use ($receipt, $actor) {
            $receipt->loadMissing('member', 'variety', 'location');

            $this->send($actor, [Role::ACCOUNTING_CLERK, Role::COOPERATIVE_MANAGER], [
                'title' => 'Harvest stored '.$receipt->receipt_code,
                'message' => number_format((float) $receipt->net_weight_kg, 2).' kg of '
                    .($receipt->variety?->variety_name ?? 'harvest').' from '
                    .($receipt->member?->full_name ?? 'a member').' was stored in '
                    .($receipt->location?->location_name ?? 'a storage location').'.',
                'module' => 'inventory',
                'record_ref' => $receipt->receipt_code,
                'url' => route('payments.index', [], false),
            ]);
        });
    }

    public function paymentRecorded(Payment $payment, User $actor): void
    {
        $this->guard(function () use ($payment, $actor) {
            $payment->loadMissing('member');

            $this->send($actor, [Role::COOPERATIVE_MANAGER], [
                'title' => $payment->status === 'paid' ? 'Payment paid' : 'Payment recorded',
                'message' => $payment->payment_code.' for '
                    .($payment->member?->full_name ?? 'a member').': PHP '
                    .number_format((float) $payment->final_amount, 2)
                    .' ('.str_replace('_', ' ', (string) $payment->status).').',
                'module' => 'payments',
                'record_ref' => $payment->payment_code,
                'url' => route('payments.index', [], false),
            ]);
        });
    }

    public function stockReleased(MillingRelease $release, User $actor, int $stockId): void
    {
        $this->guard(function () use ($release, $actor, $stockId) {
            $this->send($actor, [Role::COOPERATIVE_MANAGER], [
                'title' => 'Stock released '.$release->milling_order_code,
                'message' => number_format((float) $release->quantity_kg, 2).' kg released to milling by '.$actor->full_name.'.',
                'module' => 'milling_outbound',
                'record_ref' => $release->milling_order_code,
                'url' => route('stock.index', [], false),
            ]);

            $stock = StockItem::with('variety', 'location')->find($stockId);

            if ($stock && $stock->isLow()) {
                $this->send($actor, [Role::WAREHOUSE_ASSISTANT, Role::COOPERATIVE_MANAGER], [
                    'title' => 'Low stock alert',
                    'message' => ($stock->variety?->variety_name ?? 'Stock').' in '
                        .($stock->location?->location_name ?? 'storage').' is down to '
                        .number_format((float) $stock->quantity_kg, 2).' kg (threshold '
                        .number_format((float) $stock->low_stock_threshold_kg, 2).' kg).',
                    'module' => 'inventory',
                    'record_ref' => 'STOCK-'.$stock->stock_id,
                    'url' => route('stock.index', [], false),
                ]);
            }
        });
    }

    /**
     * @param  array<int, string>  $roleCodes
     * @param  array<string, mixed>  $payload
     */
    private function send(User $actor, array $roleCodes, array $payload): void
    {
        $recipients = User::query()
            ->where('branch_id', $actor->branch_id)
            ->where('is_active', true)
            ->where('user_id', '!=', $actor->user_id)
            ->whereHas('role', fn ($query) => $query->whereIn('role_code', $roleCodes))
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new RecordActivity($payload + ['actor' => $actor->full_name]));
    }

    private function guard(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}