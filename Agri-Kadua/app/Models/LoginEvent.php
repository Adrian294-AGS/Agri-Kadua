<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginEvent extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'event_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'guidelines_acknowledged' => 'boolean',
            'success' => 'boolean',
            'logged_in_at' => 'datetime',
        ];
    }
}