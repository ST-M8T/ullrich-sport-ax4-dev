<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\Queries;

use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;

/**
 * Read-model query for the admin catalog overview (PROJ-6).
 *
 * Engineering-Handbuch §10/§11: read-only port — Implementierung lebt in
 * Infrastructure, Controllers/Application sehen nur das Interface.
 *
 * Returns flat row-arrays (not aggregates) because the overview is a pure
 * presentation projection: columns code/name/status/source/synced_at/
 * routings_summary/services_count. Loading full DhlProduct VOs würde
 * unnötig Hydration kosten und Engineering-Handbuch §57 (Performance) verletzen.
 */
interface DhlCatalogProductListQuery
{
    /**
     * @return PaginatedResult<array{
     *     code: string,
     *     name: string,
     *     from_countries: list<string>,
     *     to_countries: list<string>,
     *     status: 'active'|'deprecated',
     *     source: string,
     *     synced_at: ?\DateTimeImmutable,
     *     deprecated_at: ?\DateTimeImmutable,
     *     replaced_by_code: ?string,
     *     services_count: int,
     * }>
     */
    public function paginate(DhlCatalogProductListFilter $filter): PaginatedResult;
}
