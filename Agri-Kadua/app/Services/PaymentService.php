<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DeliveryReceipt;
use App\Models\Payment;
use App\Models\ReceiptSlip;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(private TransmissionService $transmission)
    {
    }

    /**
     * Payment = accepted net kg x base rate - input advance - service fee - other deductions (FR-07).
     *
     * @return array{gross_value: float, final_amount: float}
     */
    public function calculate(float $netKg, float $ratePerKg, float $inputAdvance = 0, float $serviceFee = 0, float $otherDeductions = 0): array
    {
        if ($netKg <= 0 || $ratePerKg < 0 || $inputAdvance < 0 || $serviceFee < 0 || $otherDeductions < 0) {
            throw new InvalidArgumentException('Net weight must be positive and rate and deductions cannot be negative.');
        }

        $money = static fn (float $value): string => number_format($value, 4, '.', '');

        // Decimal-string math so cents never drift; half-up rounding to 2 places.
        $gross = bcadd(bcmul($money($netKg), $money($ratePerKg), 6), '0.005', 2);
        $deductions = bcadd(bcadd($money($inputAdvance), $money($serviceFee), 4), $money($otherDeductions), 4);
        $exact = bcsub($gross, $deductions, 4);
        $final = bcadd($exact, '0.005', 2);

        if (bccomp($exact, '0', 4) < 0) {
            throw new InvalidArgumentException('Deductions cannot exceed the harvest value.');
        }

        return ['gross_value' => (float) $gross, 'final_amount' => (float) $final];
    }

    /**
     * Records or updates the payment for a verified receipt (FR-06, FR-08).
     * Changing an existing payment requires a reason (FR-12).
     *
     * @param array{input_advance?:float|int|string, service_fee?:float|int|string, standard_deductions?:float|int|string, channel_id?:int|null, bank_reference?:string|null, status?:string, paid_at?:string|null, reason?:string|null} $data
     */
    public function record(DeliveryReceipt $receipt, array $data, User $user, ?int $terminalId = null): Payment
    {
        return DB::transaction(function () use ($receipt, $data, $user, $terminalId) {
            $receipt = DeliveryReceipt::with('variety')->whereKey($receipt->receipt_id)->lockForUpdate()->firstOrFail();

            $payable = in_array($receipt->status, [DeliveryReceipt::STATUS_COMPLETED, DeliveryReceipt::STATUS_STORED], true)
                && $receipt->verification_status !== 'unverified';

            if (! $payable) {
                throw new InvalidArgumentException('Only verified harvest receipts can be paid.');
            }

            $existing = Payment::where('receipt_id', $receipt->receipt_id)->first();

            if ($existing && $existing->status === 'paid') {
                throw new InvalidArgumentException('This receipt has already been paid.');
            }

            if ($existing && empty($data['reason'])) {
                throw new InvalidArgumentException('A reason is required when changing a payment record.');
            }

            $rate = $receipt->variety->rateOn($receipt->delivery_date->toDateString());

            if (! $rate) {
                throw new InvalidArgumentException('No purchase rate is configured for ' . $receipt->variety->variety_name . '.');
            }

            $advance = (float) ($data['input_advance'] ?? 0);
            $fee = (float) ($data['service_fee'] ?? 0);
            $other = (float) ($data['standard_deductions'] ?? 0);

            $calc = $this->calculate((float) $receipt->net_weight_kg, (float) $rate->rate_per_kg, $advance, $fee, $other);

            $status = $data['status'] ?? 'ready_to_pay';
            $isPaid = $status === 'paid';

            if ($isPaid && empty($data['bank_reference'])) {
                throw new InvalidArgumentException('A bank reference is required to record a paid payment.');
            }

            $attributes = [
                'member_id' => $receipt->member_id,
                'variety_id' => $receipt->variety_id,
                'net_weight_kg' => $receipt->net_weight_kg,
                'base_rate_per_kg' => $rate->rate_per_kg,
                'gross_value' => $calc['gross_value'],
                'input_advance' => $advance,
                'service_fee' => $fee,
                'standard_deductions' => $other,
                'final_amount' => $calc['final_amount'],
                'channel_id' => $data['channel_id'] ?? null,
                'bank_reference' => $data['bank_reference'] ?? null,
                'status' => $status,
                'paid_at' => $isPaid ? ($data['paid_at'] ?? now()) : null,
                'recorded_by' => $user->user_id,
            ];

            if ($existing) {
                $before = 'PHP ' . number_format((float) $existing->final_amount, 2) . ' (' . $existing->status . ')';
                $existing->update($attributes);
                $payment = $existing;
            } else {
                $before = null;
                $payment = Payment::create($attributes + [
                    'receipt_id' => $receipt->receipt_id,
                    'payment_code' => $this->nextPaymentCode(),
                ]);
            }

            AuditLog::record(
                module: 'payments',
                recordRef: $payment->payment_code,
                action: $isPaid ? 'Disburse Pay' : 'Record Payment',
                reason: $data['reason'] ?? 'Payment recorded',
                before: $before,
                after: 'PHP ' . number_format($calc['final_amount'], 2) . ' (' . $status . ')',
                userId: $user->user_id,
                terminalId: $terminalId,
            );

            if ($isPaid) {
                $this->issueSlip($payment, $user);
            }

            $this->transmission->confirm('payments', 'payment', $payment->payment_id, $user->user_id, $terminalId);

            return $payment->refresh();
        });
    }

    /**
     * Issues the itemized settlement receipt (FR-09). Safe to call twice.
     */
    public function issueSlip(Payment $payment, User $user): ReceiptSlip
    {
        $existing = ReceiptSlip::where('payment_id', $payment->payment_id)->first();

        if ($existing) {
            return $existing;
        }

        $code = 'REC-' . now()->format('Y-md') . '-' . str_pad((string) $payment->payment_id, 3, '0', STR_PAD_LEFT) . 'A';

        return ReceiptSlip::create([
            'slip_code' => $code,
            'receipt_id' => $payment->receipt_id,
            'payment_id' => $payment->payment_id,
            'barcode_value' => '*' . str_replace('-', '', $code) . '*',
            'issued_by' => $user->user_id,
        ]);
    }

    private function nextPaymentCode(): string
    {
        $last = Payment::orderByDesc('payment_id')->lockForUpdate()->value('payment_code');
        $next = $last ? ((int) substr($last, 4)) + 1 : 882110;

        return 'REC-' . $next;
    }
}