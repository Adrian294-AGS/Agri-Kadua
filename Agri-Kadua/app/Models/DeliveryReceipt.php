<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeliveryReceipt extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_STORED = 'stored';
    public const STATUS_VOIDED = 'voided';

    protected $primaryKey = 'receipt_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'gross_weight_kg' => 'decimal:2',
            'moisture_pct' => 'decimal:2',
            'impurities_pct' => 'decimal:2',
            'moisture_limit_pct' => 'decimal:2',
            'impurity_limit_pct' => 'decimal:2',
            'deduction_pct' => 'decimal:2',
            'deduction_kg' => 'decimal:2',
            'rejected_kg' => 'decimal:2',
            'net_weight_kg' => 'decimal:2',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(FarmerMember::class, 'member_id', 'member_id');
    }

    public function variety(): BelongsTo
    {
        return $this->belongsTo(CropVariety::class, 'variety_id', 'variety_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ScheduledDelivery::class, 'schedule_id', 'schedule_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'location_id', 'location_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by', 'user_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'receipt_id', 'receipt_id');
    }

    public function slip(): HasOne
    {
        return $this->hasOne(ReceiptSlip::class, 'receipt_id', 'receipt_id');
    }
}