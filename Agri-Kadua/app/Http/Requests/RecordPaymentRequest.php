<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole(Role::ACCOUNTING_CLERK);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'input_advance' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999'],
            'service_fee' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999'],
            'standard_deductions' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999'],
            'channel_id' => [
                'nullable',
                'integer',
                'required_if:status,paid',
                Rule::exists('payment_channels', 'channel_id')->where('is_active', true),
            ],
            'bank_reference' => ['nullable', 'required_if:status,paid', 'string', 'max:60'],
            'status' => ['nullable', 'in:ready_to_pay,paid'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
