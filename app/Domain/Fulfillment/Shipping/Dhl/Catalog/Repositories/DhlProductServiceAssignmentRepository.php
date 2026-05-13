<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProductServiceAssignment;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\AuditActor;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\CountryCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPayerCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;

/**
 * Persistence port for product-service assignments.
 *
 * `findAllowedServicesFor` resolves the routing-specificity tiebreak on
 * SQL level (single query) — see EloquentDhlProductServiceAssignmentRepository
 * for the concrete implementation. Domain callers never see the query shape.
 */
interface DhlProductServiceAssignmentRepository
{
    /**
     * Return all assignments that apply to the given product + routing + payer
     * tuple, with the more specific assignment winning per service code.
     *
     * @return iterable<DhlProductServiceAssignment>
     */
    public function findAllowedServicesFor(
        DhlProductCode $product,
        CountryCode $from,
        CountryCode $to,
        DhlPayerCode $payer,
    ): iterable;

    /**
     * @return iterable<DhlProductServiceAssignment>
     */
    public function findByProduct(DhlProductCode $product): iterable;

    public function save(DhlProductServiceAssignment $assignment, AuditActor $actor): void;

    public function delete(DhlProductServiceAssignment $assignment, AuditActor $actor): void;
}
