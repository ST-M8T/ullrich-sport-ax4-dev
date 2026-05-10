<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Masterdata\Contracts;

use App\Domain\Fulfillment\Masterdata\FulfillmentPackagingProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;

interface FulfillmentPackagingProfileRepository
{
    /**
     * @return iterable<FulfillmentPackagingProfile>
     */
    public function all(): iterable;

    /**
     * @param  array<string,mixed>  $filters
     * @return PaginatedResult<FulfillmentPackagingProfile>
     */
    public function paginate(?int $perPage = null, array $filters = []): PaginatedResult;

    public function getById(Identifier $id): ?FulfillmentPackagingProfile;

    /**
     * @param array{
     *     package_name: string,
     *     packaging_code?: string|null,
     *     length_mm: int,
     *     width_mm: int,
     *     height_mm: int,
     *     truck_slot_units: int,
     *     max_units_per_pallet_same_recipient: int,
     *     max_units_per_pallet_mixed_recipient: int,
     *     max_stackable_pallets_same_recipient: int,
     *     max_stackable_pallets_mixed_recipient: int,
     *     notes?: string|null,
     * } $attributes
     */
    public function create(array $attributes): FulfillmentPackagingProfile;

    /**
     * @param array{
     *     package_name?: string,
     *     packaging_code?: string|null,
     *     length_mm?: int,
     *     width_mm?: int,
     *     height_mm?: int,
     *     truck_slot_units?: int,
     *     max_units_per_pallet_same_recipient?: int,
     *     max_units_per_pallet_mixed_recipient?: int,
     *     max_stackable_pallets_same_recipient?: int,
     *     max_stackable_pallets_mixed_recipient?: int,
     *     notes?: string|null,
     * } $attributes
     */
    public function update(Identifier $id, array $attributes): FulfillmentPackagingProfile;

    public function delete(Identifier $id): void;
}
