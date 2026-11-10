<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncRun extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'run_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}