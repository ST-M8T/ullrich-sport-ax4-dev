<?php

declare(strict_types=1);

namespace App\Http\Requests\Fulfillment\Masterdata\Concerns;

/**
 * Shared helper for paired Store/Update FormRequests.
 *
 * Engineering-Handbuch §15 (technische Eingabevalidierung am Rand),
 * §61 + §75.5 (DRY in Validierung): Store- und Update-Varianten desselben
 * Aggregates unterscheiden sich technisch nur durch das `sometimes`-Prefix.
 * Anstatt die Regeln zu kopieren, definieren wir sie einmalig in einem
 * Per-Aggregate-Trait und prependen `sometimes` automatisch für den
 * Update-Pfad.
 */
trait WrapsRulesAsSometimes
{
    /**
     * Prepends `sometimes` to each rule list when `$isUpdate` is true.
     * If a list already starts with `sometimes`, it is left untouched.
     *
     * @param  array<string, array<int, mixed>>  $rules
     * @return array<string, array<int, mixed>>
     */
    protected function applySometimes(array $rules, bool $isUpdate): array
    {
        if (! $isUpdate) {
            return $rules;
        }

        $wrapped = [];
        foreach ($rules as $field => $list) {
            if ($list !== [] && $list[0] === 'sometimes') {
                $wrapped[$field] = $list;

                continue;
            }
            $wrapped[$field] = array_merge(['sometimes'], $list);
        }

        return $wrapped;
    }
}
