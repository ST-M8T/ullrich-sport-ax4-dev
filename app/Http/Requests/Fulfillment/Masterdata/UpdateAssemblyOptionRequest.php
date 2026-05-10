<?php

namespace App\Http\Requests\Fulfillment\Masterdata;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAssemblyOptionRequest extends FormRequest
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
        $optionId = $this->route('assemblyOption') ?? $this->route('assembly_option');

        return [
            'assembly_item_id' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                Rule::unique('fulfillment_assembly_options', 'assembly_item_id')->ignore($optionId),
            ],
            'assembly_packaging_id' => ['sometimes', 'required', 'integer', 'min:1', 'exists:fulfillment_packaging_profiles,id'],
            'assembly_weight_kg' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
