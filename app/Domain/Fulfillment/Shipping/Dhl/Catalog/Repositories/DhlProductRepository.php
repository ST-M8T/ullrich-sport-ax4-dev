<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProduct;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\AuditActor;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use DateTimeImmutable;

/**
 * Persistence port for DhlProduct aggregates.
 *
 * Repositories own audit-logging: every mutating method takes an AuditActor
 * and is expected to record before/after diffs transactionally. Implementations
 * live in `Infrastructure/Persistence/Dhl/Catalog/`.
 */
interface DhlProductRepository
{
    public function findByCode(DhlProductCode $code): ?DhlProduct;

    /**
     * @return iterable<DhlProduct>
     */
    public function findAllActive(DateTimeImmutable $at): iterable;

    /**
     * @return iterable<DhlProduct>
     */
    public function findDeprecatedSince(DateTimeImmutable $since): iterable;

    public function save(DhlProduct $product, AuditActor $actor): void;

    public function softDeprecate(
        DhlProductCode $code,
        ?DhlProductCode $successor,
        AuditActor $actor,
    ): void;

    public function restore(DhlProductCode $code, AuditActor $actor): void;

    /**
     * Updates only the `replaced_by_code` field of an existing product without
     * altering its deprecation state. Used by the successor-mapping admin
     * commands (PROJ-6) where ops staff manually pin a successor for a
     * deprecated product (or clear a mistakenly set one).
     *
     * Writes an audit-log entry inside the same transaction. Implementations
     * MUST be no-op safe (if the value already equals the target, no audit row
     * is written — idempotency of the audit logger handles this).
     */
    public function updateSuccessor(
        DhlProductCode $code,
        ?DhlProductCode $successor,
        AuditActor $actor,
    ): void;

    public function existsByCode(DhlProductCode $code): bool;
}
