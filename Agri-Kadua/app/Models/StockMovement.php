<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $primaryKey = 'movement_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity_kg' => 'decimal:2',
        ];
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_id', 'stock_id');
    }
}