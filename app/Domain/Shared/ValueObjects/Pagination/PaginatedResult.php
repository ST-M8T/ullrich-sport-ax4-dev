<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects\Pagination;

/**
 * Value Object representing a paginated result set.
 * Immutable.
 *
 * @template T
 */
final class PaginatedResult
{
    /**
     * @param  array<T>  $items
     */
    private function __construct(
        private readonly array $items,
        private readonly int $total,
        private readonly int $perPage,
        private readonly int $currentPage,
        private readonly int $lastPage,
    ) {}

    /**
     * @template T2
     *
     * @param  array<T2>  $items
     * @return self<T2>
     */
    public static function create(
        array $items,
        int $total,
        int $perPage,
        int $currentPage,
        int $lastPage,
    ): self {
        return new self($items, $total, $perPage, $currentPage, $lastPage);
    }

    /**
     * @template T2
     *
     * @param  iterable<T2>  $items
     * @return self<T2>
     */
    public static function fromIterable(
        iterable $items,
        int $total,
        int $perPage,
        int $currentPage,
        int $lastPage,
    ): self {
        return new self(is_array($items) ? $items : iterator_to_array($items, false), $total, $perPage, $currentPage, $lastPage);
    }

    /**
     * @return array<T>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return $this->lastPage;
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function isFirstPage(): bool
    {
        return $this->currentPage === 1;
    }

    public function isLastPage(): bool
    {
        return $this->currentPage >= $this->lastPage;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function hasItems(): bool
    {
        return count($this->items) > 0;
    }

    /**
     * Returns a paginator-compatible wrapper for use in views.
     *
     * @return PaginatorLinkGenerator<T>
     */
    public function toLinks(string $route, array $routeParams = []): PaginatorLinkGenerator
    {
        return new PaginatorLinkGenerator($this, $route, $routeParams);
    }
}
