<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Fulfillment\Integrations\Dhl\Mappers\DhlAdditionalServiceMapper;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Queries\DhlCatalogAuditLogQuery;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Queries\DhlCatalogProductListQuery;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlAdditionalServiceRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlCatalogSyncStatusRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlProductRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlProductServiceAssignmentRepository;
use App\Infrastructure\Persistence\Dhl\Catalog\Mappers\DhlCatalogPersistenceMapper;
use App\Infrastructure\Persistence\Dhl\Catalog\Queries\EloquentDhlCatalogAuditLogQuery;
use App\Infrastructure\Persistence\Dhl\Catalog\Queries\EloquentDhlCatalogProductListQuery;
use App\Infrastructure\Persistence\Dhl\Catalog\Repositories\EloquentDhlAdditionalServiceRepository;
use App\Infrastructure\Persistence\Dhl\Catalog\Repositories\EloquentDhlCatalogSyncStatusRepository;
use App\Infrastructure\Persistence\Dhl\Catalog\Repositories\EloquentDhlProductRepository;
use App\Infrastructure\Persistence\Dhl\Catalog\Repositories\EloquentDhlProductServiceAssignmentRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the DHL Catalog domain ports to their Eloquent infrastructure
 * implementations. Engineering-Handbuch §8: dependency direction is enforced
 * here — Application/Domain only know the interfaces.
 */
final class DhlCatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DhlCatalogPersistenceMapper::class);

        $this->app->bind(DhlProductRepository::class, EloquentDhlProductRepository::class);
        $this->app->bind(DhlAdditionalServiceRepository::class, EloquentDhlAdditionalServiceRepository::class);
        $this->app->bind(
            DhlProductServiceAssignmentRepository::class,
            EloquentDhlProductServiceAssignmentRepository::class,
        );
        $this->app->bind(
            DhlCatalogSyncStatusRepository::class,
            EloquentDhlCatalogSyncStatusRepository::class,
        );

        // Read-model queries (CQRS read side) for the admin inspection
        // surface (PROJ-6). Engineering-Handbuch §10/§11 — Datenzugriff
        // lebt in Infrastructure, Controller sehen nur die Interfaces.
        $this->app->bind(DhlCatalogProductListQuery::class, EloquentDhlCatalogProductListQuery::class);
        $this->app->bind(DhlCatalogAuditLogQuery::class, EloquentDhlCatalogAuditLogQuery::class);

        // PROJ-3: zentraler Mapper für DhlServiceOptionCollection → API-Payload.
        // Strict-Validation-Flag aus config (Default false während Rollout).
        $this->app->singleton(DhlAdditionalServiceMapper::class, function ($app): DhlAdditionalServiceMapper {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return new DhlAdditionalServiceMapper(
                assignmentRepository: $app->make(DhlProductServiceAssignmentRepository::class),
                serviceRepository: $app->make(DhlAdditionalServiceRepository::class),
                logger: Log::channel('dhl-catalog'),
                strictValidation: (bool) $config->get('dhl-catalog.strict_validation', false),
            );
        });
    }
}
