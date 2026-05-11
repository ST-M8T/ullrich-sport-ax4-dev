<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Masterdata\Contracts;

use App\Domain\Fulfillment\Masterdata\FulfillmentFreightProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;

interface FulfillmentFreightProfileRepository
{
    /**
     * @return iterable<FulfillmentFreightProfile>
     */
    public function all(): iterable;

    /**
     * @param  array<string,mixed>  $filters
     * @return PaginatedResult<FulfillmentFreightProfile>
     */
    public function paginate(?int $perPage = null, array $filters = []): PaginatedResult;

    public function getById(Identifier $shippingProfileId): ?FulfillmentFreightProfile;

    /**
     * @param array{
     *     shipping_profile_id: int,
     *     label?: string|null,
     *     dhl_product_id?: string|null,
     *     dhl_default_service_codes?: array<int, string>|null,
     *     shipping_method_mapping?: array<string, array{product_id: string, service_codes?: array<int, string>}>|null,
     *     account_number?: string|null,
     * } $attributes
     */
    public function create(array $attributes): FulfillmentFreightProfile;

    /**
     * @param array{
     *     label?: string|null,
     *     dhl_product_id?: string|null,
     *     dhl_default_service_codes?: array<int, string>|null,
     *     shipping_method_mapping?: array<string, array{product_id: string, service_codes?: array<int, string>}>|null,
     *     account_number?: string|null,
     * } $attributes
     */
    public function update(Identifier $shippingProfileId, array $attributes): FulfillmentFreightProfile;

    public function delete(Identifier $shippingProfileId): void;
}
