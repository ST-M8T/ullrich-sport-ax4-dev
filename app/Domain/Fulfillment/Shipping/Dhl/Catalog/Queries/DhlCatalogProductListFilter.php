<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\Queries;

/**
 * Immutable filter value object for the catalog product list query.
 *
 * Engineering-Handbuch §14: DTO without behaviour. Constructor enforces
 * the validation invariants the Form Request already pre-validated.
 */
final readonly class DhlCatalogProductListFilter
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DEPRECATED = 'deprecated';

    public const SOURCE_SEED = 'seed';
    public const SOURCE_API = 'api';
    public const SOURCE_MANUAL = 'manual';

    /**
     * @param  list<string>  $fromCountries  ISO-3166 alpha-2, uppercase
     * @param  list<string>  $toCountries    ISO-3166 alpha-2, uppercase
     */
    public function __construct(
        public array $fromCountries = [],
        public array $toCountries = [],
        public ?string $status = null,
        public ?string $source = null,
        public ?string $search = null,
        public int $page = 1,
        public int $perPage = 25,
    ) {
        if ($page < 1) {
            throw new \InvalidArgumentException('page must be >= 1.');
        }
        if ($perPage < 1 || $perPage > 200) {
            throw new \InvalidArgumentException('perPage must be 1..200.');
        }
        if ($status !== null && ! in_array($status, [self::STATUS_ACTIVE, self::STATUS_DEPRECATED], true)) {
            throw new \InvalidArgumentException('status must be active|deprecated.');
        }
        if ($source !== null && ! in_array($source, [self::SOURCE_SEED, self::SOURCE_API, self::SOURCE_MANUAL], true)) {
            throw new \InvalidArgumentException('source must be seed|api|manual.');
        }
    }
}
