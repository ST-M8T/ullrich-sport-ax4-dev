<?php

declare(strict_types=1);

namespace App\Support\Persistence;

use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Hilft Eloquent-Repositories beim typsicheren Mapping von Models zu Domain-Entities.
 *
 * Hintergrund: Eloquent's `Collection<int, Model>::map(callable)` und
 * `LengthAwarePaginator::through(callable)` sind über das generische
 * `Model`-Basistyp parametrisiert. Eine Closure mit spezifischer
 * `XxxModel`-Signatur wird von PhpStan dann als Type-Mismatch gemeldet.
 *
 * Dieser Trait kapselt das Mapping mit `@template`-Generics, sodass
 * PhpStan den spezifischen Model-Typ kennt und der Mismatch verschwindet.
 *
 * Beispiel:
 * ```php
 * return $this->mapEloquentCollection(
 *     $query->get(),
 *     fn (SystemJobModel $model) => $this->mapModel($model),
 * );
 * ```
 */
trait MapsEloquentModels
{
    /**
     * @template TModel of Model
     * @template TDomain
     *
     * @param  EloquentCollection<int, TModel>  $collection
     * @param  callable(TModel): TDomain  $mapper
     * @return Collection<int, TDomain>
     */
    private function mapEloquentCollection(EloquentCollection $collection, callable $mapper): Collection
    {
        $result = [];
        foreach ($collection as $model) {
            $result[] = $mapper($model);
        }

        return new Collection($result);
    }

    /**
     * @template TModel of Model
     * @template TDomain
     *
     * @param  LengthAwarePaginator<int, TModel>  $paginator
     * @param  callable(TModel): TDomain  $mapper
     * @return LengthAwarePaginator<int, TDomain>
     */
    private function mapEloquentPaginator(LengthAwarePaginator $paginator, callable $mapper): LengthAwarePaginator
    {
        // through() ist die Eloquent-API für Pagination-Mapping; PhpStan kennt
        // den Generic-Typ bei expliziter @param-Annotation am Aufrufer.
        /** @var LengthAwarePaginator<int, TDomain> $mapped */
        $mapped = $paginator->through($mapper);

        return $mapped;
    }

    /**
     * @template TModel of Model
     * @template TDomain
     *
     * @param  LengthAwarePaginator<int, TModel>  $paginator
     * @param  callable(TModel): TDomain  $mapper
     * @return PaginatedResult<TDomain>
     */
    private function mapEloquentToPaginatedResult(LengthAwarePaginator $paginator, callable $mapper): PaginatedResult
    {
        /** @var array<TDomain> $items */
        $items = [];
        foreach ($paginator->items() as $model) {
            $items[] = $mapper($model);
        }

        return PaginatedResult::create(
            items: $items,
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
        );
    }
}
