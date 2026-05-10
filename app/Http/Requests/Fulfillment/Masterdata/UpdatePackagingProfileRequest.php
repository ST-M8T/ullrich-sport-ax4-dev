<?php

namespace App\Http\Requests\Fulfillment\Masterdata;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePackagingProfileRequest extends FormRequest
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
        $profileId = $this->route('packagingProfile') ?? $this->route('packaging_profile');

        return [
            'package_name' => ['sometimes', 'required', 'string', 'max:255'],
            'packaging_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:64',
                Rule::unique('fulfillment_packaging_profiles', 'packaging_code')->ignore($profileId),
            ],
            'length_mm' => ['sometimes', 'required', 'integer', 'min:1', 'max:20000'],
            'width_mm' => ['sometimes', 'required', 'integer', 'min:1', 'max:20000'],
            'height_mm' => ['sometimes', 'required', 'integer', 'min:1', 'max:20000'],
            'truck_slot_units' => ['sometimes', 'required', 'integer', 'min:1', 'max:255'],
            'max_units_per_pallet_same_recipient' => ['sometimes', 'required', 'integer', 'min:1', 'max:5000'],
            'max_units_per_pallet_mixed_recipient' => ['sometimes', 'required', 'integer', 'min:1', 'max:5000'],
            'max_stackable_pallets_same_recipient' => ['sometimes', 'required', 'integer', 'min:1', 'max:500'],
            'max_stackable_pallets_mixed_recipient' => ['sometimes', 'required', 'integer', 'min:1', 'max:500'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
