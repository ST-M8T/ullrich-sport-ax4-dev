<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Masterdata\Contracts;

use App\Domain\Fulfillment\Masterdata\FulfillmentSenderProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;

interface FulfillmentSenderProfileRepository
{
    /**
     * @return iterable<FulfillmentSenderProfile>
     */
    public function all(): iterable;

    /**
     * @param  array<string,mixed>  $filters
     * @return PaginatedResult<FulfillmentSenderProfile>
     */
    public function paginate(?int $perPage = null, array $filters = []): PaginatedResult;

    public function findByCode(string $senderCode): ?FulfillmentSenderProfile;

    public function getById(Identifier $id): ?FulfillmentSenderProfile;

    /**
     * @param array{
     *     sender_code: string,
     *     display_name: string,
     *     company_name: string,
     *     contact_person?: string|null,
     *     email?: string|null,
     *     phone?: string|null,
     *     street_name: string,
     *     street_number?: string|null,
     *     address_addition?: string|null,
     *     postal_code: string,
     *     city: string,
     *     country_iso2: string,
     * } $attributes
     */
    public function create(array $attributes): FulfillmentSenderProfile;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Identifier $id, array $attributes): FulfillmentSenderProfile;

    public function delete(Identifier $id): void;
}
