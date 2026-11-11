<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class StoreReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole(Role::WAREHOUSE_ASSISTANT);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'exists:farmer_members,member_id'],
            'variety_id' => ['required', 'integer', 'exists:crop_varieties,variety_id'],
            'schedule_id' => ['nullable', 'integer', 'exists:scheduled_deliveries,schedule_id'],
            'delivery_date' => ['required', 'date', 'before_or_equal:today'],
            'gross_weight_kg' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999'],
            'moisture_pct' => ['nullable', 'numeric', 'decimal:0,2', 'between:0,100'],
            'impurities_pct' => ['nullable', 'numeric', 'decimal:0,2', 'between:0,100'],
            'deduction_pct' => ['nullable', 'numeric', 'decimal:0,2', 'between:0,100'],
            'rejected_kg' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
