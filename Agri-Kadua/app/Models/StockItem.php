<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockItem extends Model
{
    protected $primaryKey = 'stock_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity_kg' => 'decimal:2',
            'low_stock_threshold_kg' => 'decimal:2',
        ];
    }

    public function variety(): BelongsTo
    {
        return $this->belongsTo(CropVariety::class, 'variety_id', 'variety_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'location_id', 'location_id');
    }

    public function isLow(): bool
    {
        return bccomp((string) $this->quantity_kg, (string) $this->low_stock_threshold_kg, 2) <= 0;
    }
}