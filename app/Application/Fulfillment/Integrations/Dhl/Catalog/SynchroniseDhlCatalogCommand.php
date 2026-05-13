<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Catalog;

/**
 * Immutable command DTO for {@see SynchroniseDhlCatalogService::execute()}.
 *
 * Engineering-Handbuch §14: pure data carrier — no behaviour beyond construction.
 */
final readonly class SynchroniseDhlCatalogCommand
{
    public function __construct(
        /**
         * Routing filter in the form "FROM-TO" (e.g. "DE-AT"). NULL = all
         * configured routings from `config('dhl-catalog.default_countries')`.
         */
        public ?string $routingFilter = null,
        public bool $dryRun = false,
        public string $actor = 'system:dhl-sync',
        /** When true, sourcing reads JSON fixtures instead of the live API. */
        public bool $useFixtures = false,
    ) {}
}
