<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Geteilte Filter-/Pagination-Helper für die Masterdata-Controller im
 * Fulfillment-Bounded-Context.
 *
 * Hintergrund: Mehrere Controller (Assembly, Freight, Packaging, Sender,
 * Variation) lesen identische Query-Parameter (`per_page`, generische
 * Such- und Integer-Filter) und verarbeiten sie auf exakt die gleiche
 * Weise. Dieselben privaten Helper waren bisher in jedem Controller
 * dupliziert (DRY-Verstoß, §61 / §75 Engineering-Handbuch).
 *
 * Der Trait kapselt **nur** die wirklich identischen Helper. Spezielle
 * Varianten (`normaliseCountry`, `normaliseIntAllowZero`) bleiben bewusst
 * lokal in den jeweiligen Controllern — keine zwanghafte Abstraktion
 * ohne mehrfache Verwendung (KISS, §62).
 */
trait MasterdataControllerHelpers
{
    protected function perPage(Request $request): int
    {
        $default = max(1, (int) config('performance.masterdata.page_size', 25));

        return max(1, min(200, (int) $request->query('per_page', $default)));
    }

    protected function normaliseInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    protected function normaliseSearch(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
