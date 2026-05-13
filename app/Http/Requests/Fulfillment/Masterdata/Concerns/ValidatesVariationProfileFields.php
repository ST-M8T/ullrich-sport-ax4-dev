<?php

declare(strict_types=1);

namespace App\Http\Requests\Fulfillment\Masterdata\Concerns;

/**
 * Shared field rules for Store/UpdateVariationProfileRequest.
 *
 * Engineering-Handbuch §15 + §75.5: DRY in Validierung.
 */
trait ValidatesVariationProfileFields
{
    use WrapsRulesAsSometimes;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function variationProfileFieldRules(bool $isUpdate): array
    {
        return $this->applySometimes([
            'item_id' => ['required', 'integer', 'min:1'],
            'variation_id' => ['nullable', 'integer', 'min:1'],
            'variation_name' => ['nullable', 'string', 'max:255'],
            'default_state' => ['required', 'string', 'in:kit,assembled'],
            'default_packaging_id' => ['required', 'integer', 'min:1', 'exists:fulfillment_packaging_profiles,id'],
            'default_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'assembly_option_id' => ['nullable', 'integer', 'min:1', 'exists:fulfillment_assembly_options,id'],
        ], $isUpdate);
    }
}
