<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduledDelivery extends Model
{
    protected $primaryKey = 'schedule_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'estimated_kg' => 'decimal:2',
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

    public function changes(): HasMany
    {
        return $this->hasMany(ScheduleChange::class, 'schedule_id', 'schedule_id');
    }
}