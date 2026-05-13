<?php

declare(strict_types=1);

namespace App\Http\Requests\Fulfillment\Masterdata\Concerns;

/**
 * Shared field rules for Store/UpdateSenderRuleRequest.
 *
 * Engineering-Handbuch §15 + §75.5: DRY in Validierung.
 *
 * Note: `is_active` differs subtly between Store (`nullable, boolean`) and
 * Update (`boolean`) in the original code. Both effectively accept missing
 * or boolean — the consolidated rule `['nullable', 'boolean']` (with
 * `sometimes` prepended on update) preserves the original semantics for
 * both paths.
 */
trait ValidatesSenderRuleFields
{
    use WrapsRulesAsSometimes;

    /**
     * @return array<int, string>
     */
    protected function allowedSenderRuleTypes(): array
    {
        return [
            'billing_email_contains',
            'plenty_id_equals',
            'customer_id_equals',
            'shipping_country_equals',
            'order_total_greater',
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function senderRuleFieldRules(bool $isUpdate): array
    {
        return $this->applySometimes([
            'priority' => ['required', 'integer', 'min:0', 'max:1000'],
            'rule_type' => ['required', 'string', 'in:'.implode(',', $this->allowedSenderRuleTypes())],
            'match_value' => ['required', 'string', 'max:255'],
            'target_sender_id' => ['required', 'integer', 'min:1', 'exists:fulfillment_sender_profiles,id'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
        ], $isUpdate);
    }
}
