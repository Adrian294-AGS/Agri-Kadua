<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptSlip extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'slip_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'last_printed_at' => 'datetime',
            'member_signed' => 'boolean',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(DeliveryReceipt::class, 'receipt_id', 'receipt_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'payment_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by', 'user_id');
    }
}