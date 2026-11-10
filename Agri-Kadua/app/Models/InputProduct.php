<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InputProduct extends Model
{
    protected $primaryKey = 'product_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'subsidy_rate_pct' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InputCategory::class, 'category_id', 'category_id');
    }
}