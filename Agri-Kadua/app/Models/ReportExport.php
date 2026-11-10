<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'export_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'generated_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'template_id', 'template_id');
    }
}