<?php

namespace App\Http\Requests\Fulfillment\Masterdata;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePackagingProfileRequest extends FormRequest
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
            'package_name' => ['required', 'string', 'max:255'],
            'packaging_code' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('fulfillment_packaging_profiles', 'packaging_code'),
            ],
            'length_mm' => ['required', 'integer', 'min:1', 'max:20000'],
            'width_mm' => ['required', 'integer', 'min:1', 'max:20000'],
            'height_mm' => ['required', 'integer', 'min:1', 'max:20000'],
            'truck_slot_units' => ['required', 'integer', 'min:1', 'max:255'],
            'max_units_per_pallet_same_recipient' => ['required', 'integer', 'min:1', 'max:5000'],
            'max_units_per_pallet_mixed_recipient' => ['required', 'integer', 'min:1', 'max:5000'],
            'max_stackable_pallets_same_recipient' => ['required', 'integer', 'min:1', 'max:500'],
            'max_stackable_pallets_mixed_recipient' => ['required', 'integer', 'min:1', 'max:500'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
