<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncModuleStatus extends Model
{
    public $timestamps = false;

    protected $table = 'sync_module_status';

    protected $primaryKey = 'module_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_checked_at' => 'datetime',
        ];
    }
}