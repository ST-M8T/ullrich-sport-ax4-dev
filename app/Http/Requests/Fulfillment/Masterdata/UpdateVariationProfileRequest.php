<?php

namespace App\Http\Requests\Fulfillment\Masterdata;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateVariationProfileRequest extends FormRequest
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
            'item_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'variation_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'variation_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'default_state' => ['sometimes', 'required', 'string', 'in:kit,assembled'],
            'default_packaging_id' => ['sometimes', 'required', 'integer', 'min:1', 'exists:fulfillment_packaging_profiles,id'],
            'default_weight_kg' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999'],
            'assembly_option_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'exists:fulfillment_assembly_options,id'],
        ];
    }
}
