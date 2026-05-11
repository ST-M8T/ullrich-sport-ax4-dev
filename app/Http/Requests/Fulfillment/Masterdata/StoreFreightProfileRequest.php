<?php

namespace App\Http\Requests\Fulfillment\Masterdata;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreFreightProfileRequest extends FormRequest
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
            'shipping_profile_id' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('fulfillment_freight_profiles', 'shipping_profile_id'),
            ],
            'label' => ['nullable', 'string', 'max:255'],
            'dhl_product_id' => ['nullable', 'string', 'max:32'],
            'dhl_default_service_codes' => ['nullable', 'array'],
            'dhl_default_service_codes.*' => ['string', 'max:16'],
            'shipping_method_mapping' => ['nullable', 'array'],
            'shipping_method_mapping.*' => ['array'],
            'shipping_method_mapping.*.product_id' => ['required_with:shipping_method_mapping', 'string', 'max:32'],
            'shipping_method_mapping.*.service_codes' => ['nullable', 'array'],
            'shipping_method_mapping.*.service_codes.*' => ['string', 'max:16'],
            'account_number' => ['nullable', 'string', 'max:32'],
        ];
    }
}
