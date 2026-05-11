<?php

namespace App\Http\Requests\Fulfillment\Masterdata;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateFreightProfileRequest extends FormRequest
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
            'label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dhl_product_id' => ['sometimes', 'nullable', 'string', 'max:32'],
            'dhl_default_service_codes' => ['sometimes', 'nullable', 'array'],
            'dhl_default_service_codes.*' => ['string', 'max:16'],
            'shipping_method_mapping' => ['sometimes', 'nullable', 'array'],
            'shipping_method_mapping.*' => ['array'],
            'shipping_method_mapping.*.product_id' => ['required_with:shipping_method_mapping', 'string', 'max:32'],
            'shipping_method_mapping.*.service_codes' => ['nullable', 'array'],
            'shipping_method_mapping.*.service_codes.*' => ['string', 'max:16'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
