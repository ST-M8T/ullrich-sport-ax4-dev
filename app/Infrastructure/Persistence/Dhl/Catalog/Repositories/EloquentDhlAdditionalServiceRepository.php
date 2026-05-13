<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dhl\Catalog\Repositories;

use App\Application\Fulfillment\Integrations\Dhl\Catalog\DhlCatalogAuditLogger;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlAdditionalService;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlAdditionalServiceRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\AuditActor;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlServiceCategory;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlAdditionalServiceModel;
use App\Infrastructure\Persistence\Dhl\Catalog\Mappers\DhlCatalogPersistenceMapper;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentDhlAdditionalServiceRepository implements DhlAdditionalServiceRepository
{
    public function __construct(
        private readonly DhlCatalogPersistenceMapper $mapper,
        private readonly DhlCatalogAuditLogger $auditLogger,
    ) {
    }

    public function findByCode(string $serviceCode): ?DhlAdditionalService
    {
        $model = DhlAdditionalServiceModel::query()->whereKey($serviceCode)->first();

        return $model !== null ? $this->mapper->toServiceEntity($model) : null;
    }

    public function findAllActive(): iterable
    {
        $models = DhlAdditionalServiceModel::query()
            ->whereNull('deprecated_at')
            ->get();

        foreach ($models as $m) {
            yield $this->mapper->toServiceEntity($m);
        }
    }

    public function findByCategory(DhlServiceCategory $category): iterable
    {
        $models = DhlAdditionalServiceModel::query()
            ->where('category', $category->value)
            ->get();

        foreach ($models as $m) {
            yield $this->mapper->toServiceEntity($m);
        }
    }

    public function save(DhlAdditionalService $service, AuditActor $actor): void
    {
        DB::transaction(function () use ($service, $actor): void {
            $existing = DhlAdditionalServiceModel::query()->whereKey($service->code())->first();
            $before = $existing !== null ? $this->mapper->toServiceEntity($existing) : null;

            $model = $this->mapper->toServiceModel($service, $existing);
            $model->save();

            $action = $before === null
                ? DhlCatalogAuditLogger::ACTION_CREATED
                : DhlCatalogAuditLogger::ACTION_UPDATED;

            $this->auditLogger->recordServiceChange(
                action: $action,
                entityKey: $service->code(),
                before: $before,
                after: $service,
                actor: $actor,
            );
        });
    }

    public function softDeprecate(string $serviceCode, AuditActor $actor): void
    {
        DB::transaction(function () use ($serviceCode, $actor): void {
            $existing = DhlAdditionalServiceModel::query()->whereKey($serviceCode)->first();
            if ($existing === null) {
                return;
            }
            $before = $this->mapper->toServiceEntity($existing);
            $after = $this->mapper->toServiceEntity($existing);
            $after->deprecate(new DateTimeImmutable);

            $model = $this->mapper->toServiceModel($after, $existing);
            $model->save();

            $this->auditLogger->recordServiceChange(
                action: DhlCatalogAuditLogger::ACTION_DEPRECATED,
                entityKey: $serviceCode,
                before: $before,
                after: $after,
                actor: $actor,
            );
        });
    }

    public function restore(string $serviceCode, AuditActor $actor): void
    {
        DB::transaction(function () use ($serviceCode, $actor): void {
            $existing = DhlAdditionalServiceModel::query()->whereKey($serviceCode)->first();
            if ($existing === null) {
                return;
            }
            $before = $this->mapper->toServiceEntity($existing);
            $after = $this->mapper->toServiceEntity($existing);
            $after->restore();

            $model = $this->mapper->toServiceModel($after, $existing);
            $model->save();

            $this->auditLogger->recordServiceChange(
                action: DhlCatalogAuditLogger::ACTION_RESTORED,
                entityKey: $serviceCode,
                before: $before,
                after: $after,
                actor: $actor,
            );
        });
    }
}
