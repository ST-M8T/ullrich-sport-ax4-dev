<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Masterdata\Contracts;

use App\Domain\Fulfillment\Masterdata\FulfillmentSenderRule;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;

interface FulfillmentSenderRuleRepository
{
    /**
     * @return iterable<FulfillmentSenderRule>
     */
    public function all(): iterable;

    /**
     * @param  array<string,mixed>  $filters
     * @return PaginatedResult<FulfillmentSenderRule>
     */
    public function paginate(?int $perPage = null, array $filters = []): PaginatedResult;

    /**
     * @return iterable<FulfillmentSenderRule>
     */
    public function findBySender(Identifier $senderId): iterable;

    public function getById(Identifier $id): ?FulfillmentSenderRule;

    /**
     * @param array{
     *     priority: int,
     *     rule_type: string,
     *     match_value: string,
     *     target_sender_id: int,
     *     is_active: bool,
     *     description?: string|null,
     * } $attributes
     */
    public function create(array $attributes): FulfillmentSenderRule;

    /**
     * @param array{
     *     priority?: int,
     *     rule_type?: string,
     *     match_value?: string,
     *     target_sender_id?: int,
     *     is_active?: bool,
     *     description?: string|null,
     * } $attributes
     */
    public function update(Identifier $id, array $attributes): FulfillmentSenderRule;

    public function delete(Identifier $id): void;
}
