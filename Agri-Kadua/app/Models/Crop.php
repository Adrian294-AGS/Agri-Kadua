<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Crop extends Model
{
    protected $primaryKey = 'crop_id';

    protected $guarded = [];

    public function varieties(): HasMany
    {
        return $this->hasMany(CropVariety::class, 'crop_id', 'crop_id');
    }
}