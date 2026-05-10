<?php

namespace App\Http\Requests\Fulfillment\Masterdata;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAssemblyOptionRequest extends FormRequest
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
            'assembly_item_id' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('fulfillment_assembly_options', 'assembly_item_id'),
            ],
            'assembly_packaging_id' => ['required', 'integer', 'min:1', 'exists:fulfillment_packaging_profiles,id'],
            'assembly_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
