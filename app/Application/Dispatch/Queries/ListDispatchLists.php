<?php

declare(strict_types=1);

namespace App\Application\Dispatch\Queries;

use App\Domain\Dispatch\Contracts\DispatchListRepository;
use App\Domain\Dispatch\DispatchListPaginationResult;

final class ListDispatchLists
{
    public function __construct(private readonly DispatchListRepository $lists) {}

    /**
     * @param  array<string,mixed>  $filters
     */
    public function __invoke(int $page = 1, int $perPage = 25, array $filters = [], bool $withScans = false): DispatchListPaginationResult
    {
        return $this->lists->paginate($page, $perPage, $filters, $withScans);
    }
}
