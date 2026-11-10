<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const WAREHOUSE_ASSISTANT = 'warehouse_assistant';
    public const FIELD_COORDINATOR = 'field_coordinator';
    public const ACCOUNTING_CLERK = 'accounting_clerk';
    public const COOPERATIVE_MANAGER = 'cooperative_manager';

    protected $primaryKey = 'role_id';

    protected $guarded = [];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id', 'role_id');
    }
}