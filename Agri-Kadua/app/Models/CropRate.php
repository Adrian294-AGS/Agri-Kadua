<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CropRate extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'rate_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rate_per_kg' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function variety(): BelongsTo
    {
        return $this->belongsTo(CropVariety::class, 'variety_id', 'variety_id');
    }
}
