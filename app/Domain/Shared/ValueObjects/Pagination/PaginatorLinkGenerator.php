<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects\Pagination;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as IlluminateLengthAwarePaginator;
use Illuminate\Support\Request;

/**
 * Generates pagination links from a PaginatedResult by wrapping it in a
 * minimal LengthAwarePaginator-compatible object for the view layer.
 *
 * This allows views that expect Laravel's paginator interface to work
 * with our domain-independent PaginatedResult VO.
 */
final class PaginatorLinkGenerator
{
    private ?LengthAwarePaginator $cachedPaginator = null;

    public function __construct(
        private readonly PaginatedResult $result,
        private readonly string $route,
        private readonly array $routeParams = [],
    ) {}

    /**
     * @return LengthAwarePaginator<int, mixed>
     */
    public function paginator(): LengthAwarePaginator
    {
        if ($this->cachedPaginator !== null) {
            return $this->cachedPaginator;
        }

        $request = request();
        $queryParams = $request->query();

        $items = $this->result->items();
        $total = $this->result->total();
        $perPage = $this->result->perPage();
        $currentPage = $this->result->currentPage();
        $lastPage = $this->result->lastPage();

        $this->cachedPaginator = new IlluminateLengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'pageName' => 'page',
                'query' => $queryParams,
            ]
        );

        return $this->cachedPaginator;
    }

    public function onEachSide(int $sides = 3): self
    {
        return $this;
    }

    /**
     * Renders pagination links HTML.
     */
    public function links(): \Illuminate\Contracts\View\View
    {
        return $this->paginator()->links();
    }

    public function firstItem(): ?int
    {
        if (! $this->result->hasItems()) {
            return null;
        }

        return ($this->result->currentPage() - 1) * $this->result->perPage() + 1;
    }

    public function lastItem(): ?int
    {
        if (! $this->result->hasItems()) {
            return null;
        }

        $last = $this->result->currentPage() * $this->result->perPage();

        return min($last, $this->result->total());
    }

    public function total(): int
    {
        return $this->result->total();
    }
}
