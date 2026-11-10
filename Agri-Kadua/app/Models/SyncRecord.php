<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncRecord extends Model
{
    public const UPDATED_AT = null;

    protected $primaryKey = 'sync_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'size_kb' => 'decimal:2',
            'synced_at' => 'datetime',
        ];
    }
}