<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Masterdata\Contracts;

use App\Domain\Fulfillment\Masterdata\FulfillmentAssemblyOption;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;

interface FulfillmentAssemblyOptionRepository
{
    /**
     * @return iterable<FulfillmentAssemblyOption>
     */
    public function all(): iterable;

    /**
     * @param  array<string,mixed>  $filters
     * @return PaginatedResult<FulfillmentAssemblyOption>
     */
    public function paginate(?int $perPage = null, array $filters = []): PaginatedResult;

    public function findByAssemblyItemId(int $assemblyItemId): ?FulfillmentAssemblyOption;

    public function getById(Identifier $id): ?FulfillmentAssemblyOption;

    /**
     * @param array{
     *     assembly_item_id: int,
     *     assembly_packaging_id: int,
     *     assembly_weight_kg?: float|null,
     *     description?: string|null,
     * } $attributes
     */
    public function create(array $attributes): FulfillmentAssemblyOption;

    /**
     * @param array{
     *     assembly_item_id?: int,
     *     assembly_packaging_id?: int,
     *     assembly_weight_kg?: float|null,
     *     description?: string|null,
     * } $attributes
     */
    public function update(Identifier $id, array $attributes): FulfillmentAssemblyOption;

    public function delete(Identifier $id): void;
}
