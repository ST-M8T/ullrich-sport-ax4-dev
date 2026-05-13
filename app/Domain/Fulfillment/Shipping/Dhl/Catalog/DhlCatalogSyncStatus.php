<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog;

use DateTimeImmutable;

/**
 * Single-row value object describing the operational health of the DHL
 * catalog sync. Engineering-Handbuch §4: contains no behaviour beyond
 * read-only field access and a small invariant check on construction.
 *
 * Mutation always happens through {@see Repositories\DhlCatalogSyncStatusRepository}
 * which writes a fresh snapshot per recordAttempt/recordSuccess/recordFailure
 * call.
 */
final readonly class DhlCatalogSyncStatus
{
    public function __construct(
        public ?DateTimeImmutable $lastAttemptAt,
        public ?DateTimeImmutable $lastSuccessAt,
        public ?string $lastError,
        public int $consecutiveFailures,
        public bool $mailSentForFailureStreak,
    ) {
        if ($consecutiveFailures < 0) {
            throw new \InvalidArgumentException(
                'DhlCatalogSyncStatus.consecutiveFailures must be >= 0.'
            );
        }
    }

    public static function initial(): self
    {
        return new self(null, null, null, 0, false);
    }
}
