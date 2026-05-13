<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Catalog;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProduct;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions\DhlCatalogCircularSuccessorChainException;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions\DhlCatalogProductNotDeprecatedException;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions\DhlCatalogProductNotFoundException;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions\DhlCatalogSuccessorNotActiveException;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlProductRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\AuditActor;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use DateTimeImmutable;

/**
 * Application service that manages the manual `replaced_by_code` mapping for
 * DHL catalog products (PROJ-6). Used by the CLI commands
 * `dhl:catalog:set-successor`, `dhl:catalog:unset-successor` and
 * `dhl:catalog:list-deprecated`.
 *
 * Engineering-Handbuch §5/§7: orchestrates repository + audit; contains the
 * fail-fast invariants. Commands stay dumb adapters.
 */
final class DhlCatalogSuccessorMappingService
{
    /** Safety net against pathological data (§67 Fail-Fast). */
    public const MAX_CHAIN_DEPTH = 100;

    public function __construct(
        private readonly DhlProductRepository $products,
    ) {
    }

    /**
     * @throws DhlCatalogProductNotFoundException
     * @throws DhlCatalogProductNotDeprecatedException
     * @throws DhlCatalogSuccessorNotActiveException
     * @throws DhlCatalogCircularSuccessorChainException
     */
    public function setSuccessor(
        DhlProductCode $oldCode,
        DhlProductCode $newCode,
        AuditActor $actor,
    ): void {
        $old = $this->products->findByCode($oldCode);
        if ($old === null) {
            throw new DhlCatalogProductNotFoundException($oldCode);
        }
        if (! $old->isDeprecated()) {
            throw new DhlCatalogProductNotDeprecatedException($oldCode);
        }

        $new = $this->products->findByCode($newCode);
        if ($new === null) {
            throw new DhlCatalogProductNotFoundException($newCode);
        }
        if ($new->isDeprecated()) {
            throw new DhlCatalogSuccessorNotActiveException($newCode);
        }

        $this->guardAgainstCycle($oldCode, $newCode);

        $this->products->updateSuccessor($oldCode, $newCode, $actor);
    }

    /**
     * Clears the `replaced_by_code` field for a product. Permitted regardless
     * of deprecation state (admin may also need to clear an erroneously set
     * successor on an active product).
     *
     * @throws DhlCatalogProductNotFoundException
     */
    public function unsetSuccessor(DhlProductCode $oldCode, AuditActor $actor): void
    {
        if (! $this->products->existsByCode($oldCode)) {
            throw new DhlCatalogProductNotFoundException($oldCode);
        }

        $this->products->updateSuccessor($oldCode, null, $actor);
    }

    /**
     * Lists every deprecated product in the catalog. Returns most-recently
     * deprecated first.
     *
     * @return iterable<DhlProduct>
     */
    public function listDeprecated(): iterable
    {
        // Epoch — any product carrying a deprecated_at is included.
        return $this->products->findDeprecatedSince(new DateTimeImmutable('1970-01-01T00:00:00Z'));
    }

    /**
     * Walks the successor chain starting from `$newCode`. If we ever land on
     * `$oldCode`, the assignment would create a cycle.
     */
    private function guardAgainstCycle(DhlProductCode $oldCode, DhlProductCode $newCode): void
    {
        $chain = [$oldCode->value, $newCode->value];
        $currentCode = $newCode;

        for ($depth = 0; $depth < self::MAX_CHAIN_DEPTH; $depth++) {
            $current = $this->products->findByCode($currentCode);
            if ($current === null) {
                return;
            }
            $next = $current->replacedByCode();
            if ($next === null) {
                return;
            }
            if ($next->value === $oldCode->value) {
                $chain[] = $oldCode->value;
                throw new DhlCatalogCircularSuccessorChainException($chain);
            }
            $chain[] = $next->value;
            $currentCode = $next;
        }

        // Depth exhausted without finding a cycle that touches $oldCode — treat
        // as cycle anyway, since a well-formed chain cannot exceed this depth.
        throw new DhlCatalogCircularSuccessorChainException($chain);
    }
}
