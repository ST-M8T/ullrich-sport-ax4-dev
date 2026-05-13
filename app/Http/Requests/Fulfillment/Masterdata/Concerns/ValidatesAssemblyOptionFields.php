<?php

declare(strict_types=1);

namespace App\Http\Requests\Fulfillment\Masterdata\Concerns;

use Illuminate\Validation\Rule;

/**
 * Shared field rules for Store/UpdateAssemblyOptionRequest.
 *
 * Engineering-Handbuch §15 + §75.5: DRY in Validierung.
 */
trait ValidatesAssemblyOptionFields
{
    use WrapsRulesAsSometimes;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function assemblyOptionFieldRules(bool $isUpdate, int|string|null $ignoreId = null): array
    {
        $unique = Rule::unique('fulfillment_assembly_options', 'assembly_item_id');
        if ($ignoreId !== null) {
            $unique = $unique->ignore($ignoreId);
        }

        return $this->applySometimes([
            'assembly_item_id' => ['required', 'integer', 'min:1', $unique],
            'assembly_packaging_id' => ['required', 'integer', 'min:1', 'exists:fulfillment_packaging_profiles,id'],
            'assembly_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'description' => ['nullable', 'string', 'max:255'],
        ], $isUpdate);
    }
}
