<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldInputRequest extends Model
{
    protected $primaryKey = 'request_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subsidy_rate_pct' => 'decimal:2',
            'total_value' => 'decimal:2',
            'subsidy_value' => 'decimal:2',
            'member_share' => 'decimal:2',
            'pickup_date' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(FarmerMember::class, 'member_id', 'member_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(InputProduct::class, 'product_id', 'product_id');
    }
}