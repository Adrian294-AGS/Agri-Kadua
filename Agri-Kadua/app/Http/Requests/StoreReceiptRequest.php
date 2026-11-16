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

    public function messages(): array
    {
        return [
            'member_id.required' => 'Choose the farmer who delivered the harvest.',
            'member_id.exists' => 'Choose a valid farmer from your branch.',
            'variety_id.required' => 'Choose the crop variety being delivered.',
            'variety_id.exists' => 'Choose a valid crop variety.',
            'delivery_date.required' => 'Enter the delivery date.',
            'delivery_date.before_or_equal' => 'The delivery date cannot be in the future.',
            'gross_weight_kg.required' => 'Enter the gross harvest weight in kilograms.',
            'gross_weight_kg.gt' => 'Gross weight must be greater than zero.',
            'moisture_pct.between' => 'Moisture must be between 0 and 100 percent.',
            'impurities_pct.between' => 'Impurities must be between 0 and 100 percent.',
            'deduction_pct.between' => 'Deduction must be between 0 and 100 percent.',
            'rejected_kg.min' => 'Rejected quantity cannot be negative.',
        ];
    }
}
