<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dhl\Catalog\Queries;

use App\Application\Fulfillment\Integrations\Dhl\Catalog\DhlCatalogAuditLogger;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Queries\DhlCatalogAuditLogFilter;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Queries\DhlCatalogAuditLogQuery;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlCatalogAuditLogModel;
use DateTimeImmutable;

/**
 * Eloquent implementation of the audit-log read-model.
 */
final class EloquentDhlCatalogAuditLogQuery implements DhlCatalogAuditLogQuery
{
    public function paginate(DhlCatalogAuditLogFilter $filter): PaginatedResult
    {
        $query = DhlCatalogAuditLogModel::query();

        if ($filter->entityType !== null) {
            $query->where('entity_type', $filter->entityType);
        }
        if ($filter->action !== null) {
            $query->where('action', $filter->action);
        }
        if ($filter->actor !== null && $filter->actor !== '') {
            $needle = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $filter->actor) . '%';
            $query->where('actor', 'like', $needle);
        }
        if ($filter->from !== null) {
            $query->where('created_at', '>=', $filter->from->format('Y-m-d H:i:s'));
        }
        if ($filter->to !== null) {
            $query->where('created_at', '<=', $filter->to->format('Y-m-d H:i:s'));
        }

        $total = (clone $query)->count();
        $offset = ($filter->page - 1) * $filter->perPage;

        $models = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($filter->perPage)
            ->get();

        return PaginatedResult::create(
            items: $this->mapModels($models->all()),
            total: $total,
            perPage: $filter->perPage,
            currentPage: $filter->page,
            lastPage: max(1, (int) ceil($total / $filter->perPage)),
        );
    }

    public function latestForProduct(string $productCode, int $limit = 50): array
    {
        $models = DhlCatalogAuditLogModel::query()
            ->where('entity_type', DhlCatalogAuditLogger::ENTITY_PRODUCT)
            ->where('entity_key', $productCode)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $this->mapModels($models->all());
    }

    /**
     * @param  list<DhlCatalogAuditLogModel>  $models
     * @return list<array<string,mixed>>
     */
    private function mapModels(array $models): array
    {
        $out = [];
        foreach ($models as $m) {
            $created = $m->created_at;
            if (! $created instanceof DateTimeImmutable) {
                $created = new DateTimeImmutable((string) $created);
            }
            $out[] = [
                'id' => (int) $m->id,
                'entity_type' => (string) $m->entity_type,
                'entity_key' => (string) $m->entity_key,
                'action' => (string) $m->action,
                'actor' => (string) $m->actor,
                'diff' => is_array($m->diff) ? $m->diff : [],
                'created_at' => $created,
            ];
        }

        return $out;
    }
}
