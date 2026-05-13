<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\Queries;

use DateTimeImmutable;

/**
 * Immutable filter VO for the audit log query.
 */
final readonly class DhlCatalogAuditLogFilter
{
    public const ENTITY_TYPES = ['product', 'service', 'assignment'];
    public const ACTIONS = ['created', 'updated', 'deprecated', 'restored', 'deleted'];

    public function __construct(
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
        public ?string $entityType = null,
        public ?string $action = null,
        public ?string $actor = null,
        public int $page = 1,
        public int $perPage = 50,
    ) {
        if ($page < 1) {
            throw new \InvalidArgumentException('page must be >= 1.');
        }
        if ($perPage < 1 || $perPage > 200) {
            throw new \InvalidArgumentException('perPage must be 1..200.');
        }
        if ($entityType !== null && ! in_array($entityType, self::ENTITY_TYPES, true)) {
            throw new \InvalidArgumentException('entityType invalid.');
        }
        if ($action !== null && ! in_array($action, self::ACTIONS, true)) {
            throw new \InvalidArgumentException('action invalid.');
        }
        if ($from !== null && $to !== null && $from > $to) {
            throw new \InvalidArgumentException('from must be <= to.');
        }
    }
}
