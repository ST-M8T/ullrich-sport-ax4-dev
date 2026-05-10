<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Masterdata\Contracts;

use App\Domain\Fulfillment\Masterdata\FulfillmentVariationProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;

interface FulfillmentVariationProfileRepository
{
    /**
     * @return iterable<FulfillmentVariationProfile>
     */
    public function all(): iterable;

    /**
     * @param  array<string,mixed>  $filters
     * @return PaginatedResult<FulfillmentVariationProfile>
     */
    public function paginate(?int $perPage = null, array $filters = []): PaginatedResult;

    /**
     * @return iterable<FulfillmentVariationProfile>
     */
    public function findByItemId(int $itemId): iterable;

    public function getById(Identifier $id): ?FulfillmentVariationProfile;

    /**
     * @param array{
     *     item_id: int,
     *     variation_id?: int|null,
     *     variation_name?: string|null,
     *     default_state: string,
     *     default_packaging_id: int,
     *     default_weight_kg?: float|null,
     *     assembly_option_id?: int|null,
     * } $attributes
     */
    public function create(array $attributes): FulfillmentVariationProfile;

    /**
     * @param array{
     *     item_id?: int,
     *     variation_id?: int|null,
     *     variation_name?: string|null,
     *     default_state?: string,
     *     default_packaging_id?: int,
     *     default_weight_kg?: float|null,
     *     assembly_option_id?: int|null,
     * } $attributes
     */
    public function update(Identifier $id, array $attributes): FulfillmentVariationProfile;

    public function delete(Identifier $id): void;
}
