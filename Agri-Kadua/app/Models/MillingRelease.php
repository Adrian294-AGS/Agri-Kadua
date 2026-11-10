<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MillingRelease extends Model
{
    protected $primaryKey = 'release_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity_kg' => 'decimal:2',
            'authorised_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function variety(): BelongsTo
    {
        return $this->belongsTo(CropVariety::class, 'variety_id', 'variety_id');
    }
}