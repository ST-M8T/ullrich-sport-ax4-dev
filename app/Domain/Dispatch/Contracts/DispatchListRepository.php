<?php

declare(strict_types=1);

namespace App\Domain\Dispatch\Contracts;

use App\Domain\Dispatch\DispatchList;
use App\Domain\Dispatch\DispatchListPaginationResult;
use App\Domain\Dispatch\DispatchScan;
use App\Domain\Shared\ValueObjects\Identifier;

interface DispatchListRepository
{
    public function nextListIdentity(): Identifier;

    public function nextScanIdentity(): Identifier;

    /**
     * @param  array<string,mixed>  $filters
     */
    public function paginate(int $page, int $perPage, array $filters = [], bool $withScans = false): DispatchListPaginationResult;

    public function getById(Identifier $id): ?DispatchList;

    public function save(DispatchList $list): void;

    public function appendScan(DispatchList $list, DispatchScan $scan): DispatchList;
}
