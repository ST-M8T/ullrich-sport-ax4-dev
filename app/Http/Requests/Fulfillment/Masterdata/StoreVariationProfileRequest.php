<?php

namespace App\Http\Requests\Fulfillment\Masterdata;

use Illuminate\Foundation\Http\FormRequest;

final class StoreVariationProfileRequest extends FormRequest
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
            'item_id' => ['required', 'integer', 'min:1'],
            'variation_id' => ['nullable', 'integer', 'min:1'],
            'variation_name' => ['nullable', 'string', 'max:255'],
            'default_state' => ['required', 'string', 'in:kit,assembled'],
            'default_packaging_id' => ['required', 'integer', 'min:1', 'exists:fulfillment_packaging_profiles,id'],
            'default_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'assembly_option_id' => ['nullable', 'integer', 'min:1', 'exists:fulfillment_assembly_options,id'],
        ];
    }
}
