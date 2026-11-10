<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FarmerMember extends Model
{
    protected $primaryKey = 'member_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'farm_size_ha' => 'decimal:2',
            'expected_yield_kg' => 'decimal:2',
            'registered_on' => 'date',
        ];
    }

    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class, 'main_crop_id', 'crop_id');
    }

    public function variety(): BelongsTo
    {
        return $this->belongsTo(CropVariety::class, 'primary_variety_id', 'variety_id');
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id', 'user_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(DeliveryReceipt::class, 'member_id', 'member_id');
    }

    public function scheduledDeliveries(): HasMany
    {
        return $this->hasMany(ScheduledDelivery::class, 'member_id', 'member_id');
    }
}