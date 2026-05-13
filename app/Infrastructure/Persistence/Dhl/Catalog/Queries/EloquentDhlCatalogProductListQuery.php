<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dhl\Catalog\Queries;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Queries\DhlCatalogProductListFilter;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Queries\DhlCatalogProductListQuery;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlProductModel;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlProductServiceAssignmentModel;
use DateTimeImmutable;

/**
 * Eloquent implementation of the catalog product list read-model.
 *
 * Engineering-Handbuch §10: einziger Ort für direkten Datenzugriff der
 * Übersichts-Projektion. Liefert flache Arrays statt Aggregates — siehe
 * Interface-Doc für Begründung (Performance, keine Hydration nötig).
 */
final class EloquentDhlCatalogProductListQuery implements DhlCatalogProductListQuery
{
    public function paginate(DhlCatalogProductListFilter $filter): PaginatedResult
    {
        $query = DhlProductModel::query();

        if ($filter->status === DhlCatalogProductListFilter::STATUS_ACTIVE) {
            $query->whereNull('deprecated_at');
        } elseif ($filter->status === DhlCatalogProductListFilter::STATUS_DEPRECATED) {
            $query->whereNotNull('deprecated_at');
        }

        if ($filter->source !== null) {
            $query->where('source', $filter->source);
        }

        if ($filter->search !== null && $filter->search !== '') {
            $needle = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $filter->search) . '%';
            $query->where(function ($q) use ($needle): void {
                $q->where('code', 'like', $needle)
                    ->orWhere('name', 'like', $needle);
            });
        }

        // Routing filter is applied in PHP because from_countries/to_countries
        // are JSON arrays — SQLite (test driver) lacks reliable JSON-array
        // intersect operators. The catalog stays small (<= a few hundred
        // products) so a post-filter is acceptable (§57 — measured, not
        // premature optimisation).
        $needsRouting = $filter->fromCountries !== [] || $filter->toCountries !== [];

        if (! $needsRouting) {
            $total = (clone $query)->count();
            $offset = ($filter->page - 1) * $filter->perPage;
            $models = $query
                ->orderBy('deprecated_at')
                ->orderBy('code')
                ->offset($offset)
                ->limit($filter->perPage)
                ->get();

            $rows = $this->mapModels($models, $this->fetchAssignmentCounts($models->pluck('code')->all()));

            return PaginatedResult::create(
                items: $rows,
                total: $total,
                perPage: $filter->perPage,
                currentPage: $filter->page,
                lastPage: max(1, (int) ceil($total / $filter->perPage)),
            );
        }

        // Routing filter: load all matching, filter in memory, then paginate.
        $models = $query
            ->orderBy('deprecated_at')
            ->orderBy('code')
            ->get()
            ->filter(function ($m) use ($filter): bool {
                if ($filter->fromCountries !== []) {
                    $from = (array) ($m->from_countries ?? []);
                    if (array_intersect($filter->fromCountries, $from) === []) {
                        return false;
                    }
                }
                if ($filter->toCountries !== []) {
                    $to = (array) ($m->to_countries ?? []);
                    if (array_intersect($filter->toCountries, $to) === []) {
                        return false;
                    }
                }

                return true;
            })
            ->values();

        $total = $models->count();
        $offset = ($filter->page - 1) * $filter->perPage;
        $page = $models->slice($offset, $filter->perPage)->values();

        $rows = $this->mapModels($page, $this->fetchAssignmentCounts($page->pluck('code')->all()));

        return PaginatedResult::create(
            items: $rows,
            total: $total,
            perPage: $filter->perPage,
            currentPage: $filter->page,
            lastPage: max(1, (int) ceil($total / $filter->perPage)),
        );
    }

    /**
     * @param  list<string>  $codes
     * @return array<string,int>
     */
    private function fetchAssignmentCounts(array $codes): array
    {
        if ($codes === []) {
            return [];
        }
        $rows = DhlProductServiceAssignmentModel::query()
            ->select('product_code')
            ->selectRaw('COUNT(*) as c')
            ->whereIn('product_code', $codes)
            ->groupBy('product_code')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->product_code] = (int) $row->c;
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int,DhlProductModel>  $models
     * @param  array<string,int>  $assignmentCounts
     * @return list<array<string,mixed>>
     */
    private function mapModels($models, array $assignmentCounts): array
    {
        $out = [];
        foreach ($models as $m) {
            $code = (string) $m->code;
            $deprecatedAt = $m->deprecated_at instanceof DateTimeImmutable
                ? $m->deprecated_at
                : ($m->deprecated_at !== null ? new DateTimeImmutable((string) $m->deprecated_at) : null);
            $syncedAt = $m->synced_at instanceof DateTimeImmutable
                ? $m->synced_at
                : ($m->synced_at !== null ? new DateTimeImmutable((string) $m->synced_at) : null);

            $out[] = [
                'code' => $code,
                'name' => (string) $m->name,
                'from_countries' => array_values((array) ($m->from_countries ?? [])),
                'to_countries' => array_values((array) ($m->to_countries ?? [])),
                'status' => $deprecatedAt === null ? 'active' : 'deprecated',
                'source' => (string) $m->source,
                'synced_at' => $syncedAt,
                'deprecated_at' => $deprecatedAt,
                'replaced_by_code' => $m->replaced_by_code !== null ? (string) $m->replaced_by_code : null,
                'services_count' => $assignmentCounts[$code] ?? 0,
            ];
        }

        return $out;
    }
}
