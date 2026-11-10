<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    protected $primaryKey = 'payment_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'net_weight_kg' => 'decimal:2',
            'base_rate_per_kg' => 'decimal:2',
            'gross_value' => 'decimal:2',
            'input_advance' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'standard_deductions' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(DeliveryReceipt::class, 'receipt_id', 'receipt_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(FarmerMember::class, 'member_id', 'member_id');
    }

    public function variety(): BelongsTo
    {
        return $this->belongsTo(CropVariety::class, 'variety_id', 'variety_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(PaymentChannel::class, 'channel_id', 'channel_id');
    }

    public function slip(): HasOne
    {
        return $this->hasOne(ReceiptSlip::class, 'payment_id', 'payment_id');
    }
}