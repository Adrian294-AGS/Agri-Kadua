<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleChange extends Model
{
    protected $primaryKey = 'change_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'old_date' => 'date',
            'revised_date' => 'date',
            'transmitted_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ScheduledDelivery::class, 'schedule_id', 'schedule_id');
    }
}