<?php

declare(strict_types=1);

namespace App\Http\Requests\Fulfillment\Masterdata\Concerns;

use Illuminate\Validation\Rule;

/**
 * Shared field rules for Store/UpdateSenderProfileRequest.
 *
 * Engineering-Handbuch §15 + §75.5: DRY in Validierung.
 */
trait ValidatesSenderProfileFields
{
    use WrapsRulesAsSometimes;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function senderProfileFieldRules(bool $isUpdate, int|string|null $ignoreId = null): array
    {
        $unique = Rule::unique('fulfillment_sender_profiles', 'sender_code');
        if ($ignoreId !== null) {
            $unique = $unique->ignore($ignoreId);
        }

        return $this->applySometimes([
            'sender_code' => ['required', 'string', 'max:64', $unique],
            'display_name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'street_name' => ['required', 'string', 'max:255'],
            'street_number' => ['nullable', 'string', 'max:32'],
            'address_addition' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:32'],
            'city' => ['required', 'string', 'max:255'],
            'country_iso2' => ['required', 'string', 'size:2'],
        ], $isUpdate);
    }
}
