<?php

declare(strict_types=1);

namespace App\Http\Requests\Fulfillment\Masterdata\Concerns;

use Illuminate\Validation\Rule;

/**
 * Shared field rules for Store/UpdatePackagingProfileRequest.
 *
 * Engineering-Handbuch §15 + §75.5: DRY in Validierung.
 */
trait ValidatesPackagingProfileFields
{
    use WrapsRulesAsSometimes;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function packagingProfileFieldRules(bool $isUpdate, int|string|null $ignoreId = null): array
    {
        $unique = Rule::unique('fulfillment_packaging_profiles', 'packaging_code');
        if ($ignoreId !== null) {
            $unique = $unique->ignore($ignoreId);
        }

        return $this->applySometimes([
            'package_name' => ['required', 'string', 'max:255'],
            'packaging_code' => ['nullable', 'string', 'max:64', $unique],
            'length_mm' => ['required', 'integer', 'min:1', 'max:20000'],
            'width_mm' => ['required', 'integer', 'min:1', 'max:20000'],
            'height_mm' => ['required', 'integer', 'min:1', 'max:20000'],
            'truck_slot_units' => ['required', 'integer', 'min:1', 'max:255'],
            'max_units_per_pallet_same_recipient' => ['required', 'integer', 'min:1', 'max:5000'],
            'max_units_per_pallet_mixed_recipient' => ['required', 'integer', 'min:1', 'max:5000'],
            'max_stackable_pallets_same_recipient' => ['required', 'integer', 'min:1', 'max:500'],
            'max_stackable_pallets_mixed_recipient' => ['required', 'integer', 'min:1', 'max:500'],
            'notes' => ['nullable', 'string'],
        ], $isUpdate);
    }
}
