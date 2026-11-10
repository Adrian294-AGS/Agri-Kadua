<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}