<?php

declare(strict_types=1);

namespace App\Infrastructure\Pagination;

use App\Domain\Shared\ValueObjects\Pagination\PaginatorLinkGeneratorInterface;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Request;

/**
 * Generates pagination links from a PaginatedResult by wrapping it in a
 * LengthAwarePaginator-compatible object for the view layer.
 *
 * Lives in Infrastructure because it depends on Laravel's pagination
 * components and the current HTTP request.
 */
final class LaravelPaginatorLinkAdapter implements PaginatorLinkGeneratorInterface
{
    private ?LengthAwarePaginator $cachedPaginator = null;

    public function __construct(
        private readonly PaginatedResult $result,
        private readonly string $route,
        private readonly array $routeParams = [],
    ) {}

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

    public function onEachSide(int $sides = 3): self
    {
        // No-op: side configuration is handled on the underlying paginator
        return $this;
    }

    public function links(): \Illuminate\Contracts\View\View
    {
        return $this->paginator()->links();
    }

    private function paginator(): LengthAwarePaginator
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

        $this->cachedPaginator = new LengthAwarePaginator(
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
}