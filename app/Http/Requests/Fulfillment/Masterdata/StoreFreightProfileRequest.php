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
        ];
    }
}
