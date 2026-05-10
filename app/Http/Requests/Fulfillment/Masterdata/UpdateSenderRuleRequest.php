<?php

namespace App\Http\Requests\Fulfillment\Masterdata;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSenderRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'priority' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000'],
            'rule_type' => ['sometimes', 'required', 'string', 'in:'.implode(',', $this->allowedRuleTypes())],
            'match_value' => ['sometimes', 'required', 'string', 'max:255'],
            'target_sender_id' => ['sometimes', 'required', 'integer', 'min:1', 'exists:fulfillment_sender_profiles,id'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return array<int,string>
     */
    private function allowedRuleTypes(): array
    {
        return [
            'billing_email_contains',
            'plenty_id_equals',
            'customer_id_equals',
            'shipping_country_equals',
            'order_total_greater',
        ];
    }
}
