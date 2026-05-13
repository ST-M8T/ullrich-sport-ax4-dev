<?php

declare(strict_types=1);

namespace App\Http\Requests\Fulfillment\Masterdata\Concerns;

use Illuminate\Validation\Rule;

/**
 * Shared field rules for Store/UpdateFreightProfileRequest.
 *
 * Engineering-Handbuch §15 + §75.5: DRY in Validierung. Diese Schicht
 * deckt die scalar-Felder eines Freight-Profils ab. Die DHL-katalog-
 * spezifischen Cross-Field-Checks bleiben in `ValidatesDhlCatalogProfile`
 * (separate Verantwortung: Katalog-Lookup statt Pflichtfeld-Form).
 *
 * Note: das Store-Request erlaubt zusätzlich `shipping_profile_id`
 * (unique, required). Im Update wird das Profil per Route identifiziert
 * und nicht im Body geändert.
 */
trait ValidatesFreightProfileFields
{
    use WrapsRulesAsSometimes;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function freightProfileFieldRules(bool $isUpdate): array
    {
        return $this->applySometimes([
            'label' => ['nullable', 'string', 'max:255'],
            'dhl_product_id' => ['nullable', 'string', 'max:32'],
            'dhl_default_service_codes' => ['nullable', 'array'],
            'dhl_default_service_codes.*' => ['string', 'max:16'],
            'shipping_method_mapping' => ['nullable', 'array'],
            'shipping_method_mapping.*' => ['array'],
            'shipping_method_mapping.*.product_id' => ['required_with:shipping_method_mapping', 'string', 'max:32'],
            'shipping_method_mapping.*.service_codes' => ['nullable', 'array'],
            'shipping_method_mapping.*.service_codes.*' => ['string', 'max:16'],
            'account_number' => ['nullable', 'string', 'max:32'],
        ], $isUpdate);
    }
}
