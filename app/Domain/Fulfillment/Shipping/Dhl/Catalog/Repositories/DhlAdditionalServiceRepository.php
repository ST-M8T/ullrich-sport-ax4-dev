<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlAdditionalService;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\AuditActor;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlServiceCategory;

/**
 * Persistence port for DhlAdditionalService aggregates.
 */
interface DhlAdditionalServiceRepository
{
    public function findByCode(string $serviceCode): ?DhlAdditionalService;

    /**
     * @return iterable<DhlAdditionalService>
     */
    public function findAllActive(): iterable;

    /**
     * @return iterable<DhlAdditionalService>
     */
    public function findByCategory(DhlServiceCategory $category): iterable;

    public function save(DhlAdditionalService $service, AuditActor $actor): void;

    public function softDeprecate(string $serviceCode, AuditActor $actor): void;

    public function restore(string $serviceCode, AuditActor $actor): void;
}
