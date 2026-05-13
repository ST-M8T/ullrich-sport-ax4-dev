<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Catalog;

/**
 * Immutable result DTO returned by
 * {@see SynchroniseDhlCatalogService::execute()}.
 *
 * Engineering-Handbuch §14: explicit fields, no `mixed[]` blob.
 *
 * `errors` is a list of `{code:string, message:string, ...context}` arrays.
 * Error codes: apiUnavailable | authFailed | schemaInvalid |
 * suspiciousShrinkage | partial | bootstrapFailed | hydrationFailed.
 */
final readonly class SynchroniseDhlCatalogResult
{
    /**
     * @param  list<array<string,mixed>>  $errors
     */
    public function __construct(
        public int $productsAdded = 0,
        public int $productsUpdated = 0,
        public int $productsDeprecated = 0,
        public int $productsRestored = 0,
        public int $servicesAdded = 0,
        public int $servicesUpdated = 0,
        public int $servicesDeprecated = 0,
        public int $servicesRestored = 0,
        public int $assignmentsAdded = 0,
        public int $assignmentsUpdated = 0,
        public int $assignmentsDeprecated = 0,
        public array $errors = [],
        public int $durationMs = 0,
        public bool $suspicious = false,
        public bool $dryRun = false,
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function totalChanges(): int
    {
        return $this->productsAdded + $this->productsUpdated
            + $this->productsDeprecated + $this->productsRestored
            + $this->servicesAdded + $this->servicesUpdated
            + $this->servicesDeprecated + $this->servicesRestored
            + $this->assignmentsAdded + $this->assignmentsUpdated
            + $this->assignmentsDeprecated;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'products_added' => $this->productsAdded,
            'products_updated' => $this->productsUpdated,
            'products_deprecated' => $this->productsDeprecated,
            'products_restored' => $this->productsRestored,
            'services_added' => $this->servicesAdded,
            'services_updated' => $this->servicesUpdated,
            'services_deprecated' => $this->servicesDeprecated,
            'services_restored' => $this->servicesRestored,
            'assignments_added' => $this->assignmentsAdded,
            'assignments_updated' => $this->assignmentsUpdated,
            'assignments_deprecated' => $this->assignmentsDeprecated,
            'errors' => $this->errors,
            'duration_ms' => $this->durationMs,
            'suspicious' => $this->suspicious,
            'dry_run' => $this->dryRun,
        ];
    }
}
