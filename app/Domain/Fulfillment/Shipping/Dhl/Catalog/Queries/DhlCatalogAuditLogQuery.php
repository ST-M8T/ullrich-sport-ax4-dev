<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\Queries;

use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;

/**
 * Read-model port for the catalog audit log (PROJ-6 audit view).
 *
 * Returns flat row-arrays for direct view rendering — diff blob included.
 */
interface DhlCatalogAuditLogQuery
{
    /**
     * @return PaginatedResult<array{
     *     id: int,
     *     entity_type: string,
     *     entity_key: string,
     *     action: string,
     *     actor: string,
     *     diff: array<string,mixed>,
     *     created_at: \DateTimeImmutable,
     * }>
     */
    public function paginate(DhlCatalogAuditLogFilter $filter): PaginatedResult;

    /**
     * Convenience for the product detail "Audit"-tab: latest 50 entries for
     * a single product code (entity_type=product, entity_key=$productCode).
     *
     * @return list<array{
     *     id: int,
     *     entity_type: string,
     *     entity_key: string,
     *     action: string,
     *     actor: string,
     *     diff: array<string,mixed>,
     *     created_at: \DateTimeImmutable,
     * }>
     */
    public function latestForProduct(string $productCode, int $limit = 50): array;
}
