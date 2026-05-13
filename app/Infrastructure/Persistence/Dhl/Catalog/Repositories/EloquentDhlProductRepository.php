<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dhl\Catalog\Repositories;

use App\Application\Fulfillment\Integrations\Dhl\Catalog\DhlCatalogAuditLogger;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProduct;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlProductRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\AuditActor;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlProductModel;
use App\Infrastructure\Persistence\Dhl\Catalog\Mappers\DhlCatalogPersistenceMapper;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentDhlProductRepository implements DhlProductRepository
{
    public function __construct(
        private readonly DhlCatalogPersistenceMapper $mapper,
        private readonly DhlCatalogAuditLogger $auditLogger,
    ) {
    }

    public function findByCode(DhlProductCode $code): ?DhlProduct
    {
        $model = DhlProductModel::query()->whereKey($code->value)->first();

        return $model !== null ? $this->mapper->toProductEntity($model) : null;
    }

    public function findAllActive(DateTimeImmutable $at): iterable
    {
        $atStr = $at->format('Y-m-d H:i:s');

        $models = DhlProductModel::query()
            ->whereNull('deprecated_at')
            ->where('valid_from', '<=', $atStr)
            ->where(function ($q) use ($atStr): void {
                $q->whereNull('valid_until')->orWhere('valid_until', '>', $atStr);
            })
            ->get();

        foreach ($models as $model) {
            yield $this->mapper->toProductEntity($model);
        }
    }

    public function findDeprecatedSince(DateTimeImmutable $since): iterable
    {
        $models = DhlProductModel::query()
            ->whereNotNull('deprecated_at')
            ->where('deprecated_at', '>=', $since->format('Y-m-d H:i:s'))
            ->get();

        foreach ($models as $model) {
            yield $this->mapper->toProductEntity($model);
        }
    }

    public function save(DhlProduct $product, AuditActor $actor): void
    {
        DB::transaction(function () use ($product, $actor): void {
            $existing = DhlProductModel::query()->whereKey($product->code()->value)->first();
            $before = $existing !== null ? $this->mapper->toProductEntity($existing) : null;

            $model = $this->mapper->toProductModel($product, $existing);
            $model->save();

            $action = $before === null
                ? DhlCatalogAuditLogger::ACTION_CREATED
                : DhlCatalogAuditLogger::ACTION_UPDATED;

            $this->auditLogger->recordProductChange(
                action: $action,
                entityKey: $product->code()->value,
                before: $before,
                after: $product,
                actor: $actor,
            );
        });
    }

    public function softDeprecate(
        DhlProductCode $code,
        ?DhlProductCode $successor,
        AuditActor $actor,
    ): void {
        DB::transaction(function () use ($code, $successor, $actor): void {
            $existing = DhlProductModel::query()->whereKey($code->value)->first();
            if ($existing === null) {
                return;
            }
            $before = $this->mapper->toProductEntity($existing);
            $after = $this->mapper->toProductEntity($existing);
            $after->deprecate($successor, new DateTimeImmutable);

            $model = $this->mapper->toProductModel($after, $existing);
            $model->save();

            $this->auditLogger->recordProductChange(
                action: DhlCatalogAuditLogger::ACTION_DEPRECATED,
                entityKey: $code->value,
                before: $before,
                after: $after,
                actor: $actor,
            );
        });
    }

    public function restore(DhlProductCode $code, AuditActor $actor): void
    {
        DB::transaction(function () use ($code, $actor): void {
            $existing = DhlProductModel::query()->whereKey($code->value)->first();
            if ($existing === null) {
                return;
            }
            $before = $this->mapper->toProductEntity($existing);
            $after = $this->mapper->toProductEntity($existing);
            $after->restore();

            $model = $this->mapper->toProductModel($after, $existing);
            $model->save();

            $this->auditLogger->recordProductChange(
                action: DhlCatalogAuditLogger::ACTION_RESTORED,
                entityKey: $code->value,
                before: $before,
                after: $after,
                actor: $actor,
            );
        });
    }

    public function updateSuccessor(
        DhlProductCode $code,
        ?DhlProductCode $successor,
        AuditActor $actor,
    ): void {
        DB::transaction(function () use ($code, $successor, $actor): void {
            $existing = DhlProductModel::query()->whereKey($code->value)->first();
            if ($existing === null) {
                return;
            }
            $before = $this->mapper->toProductEntity($existing);

            $existing->replaced_by_code = $successor?->value;
            $existing->save();

            $after = $this->mapper->toProductEntity($existing);

            $this->auditLogger->recordProductChange(
                action: DhlCatalogAuditLogger::ACTION_UPDATED,
                entityKey: $code->value,
                before: $before,
                after: $after,
                actor: $actor,
            );
        });
    }

    public function existsByCode(DhlProductCode $code): bool
    {
        return DhlProductModel::query()->whereKey($code->value)->exists();
    }
}
