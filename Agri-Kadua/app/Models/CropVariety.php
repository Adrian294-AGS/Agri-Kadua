<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CropVariety extends Model
{
    protected $primaryKey = 'variety_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'moisture_limit_pct' => 'decimal:2',
            'impurity_limit_pct' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class, 'crop_id', 'crop_id');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(CropRate::class, 'variety_id', 'variety_id');
    }

    /**
     * The co-op base purchase rate per kg in force on the given date.
     */
    public function rateOn(?string $date = null): ?CropRate
    {
        $date ??= now()->toDateString();

        return $this->rates()
            ->where('effective_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();
    }
}