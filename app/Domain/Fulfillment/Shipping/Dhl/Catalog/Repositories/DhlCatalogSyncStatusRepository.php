<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlCatalogSyncStatus;
use DateTimeImmutable;

/**
 * Persistence port for the single-row `dhl_catalog_sync_status` table.
 *
 * The catalog sync (PROJ-2) uses exactly four lifecycle operations against
 * this state — they are named here instead of exposing a generic CRUD API
 * to keep the alert-idempotency invariants in one place.
 */
interface DhlCatalogSyncStatusRepository
{
    public function get(): DhlCatalogSyncStatus;

    public function recordAttempt(DateTimeImmutable $at): void;

    public function recordSuccess(DateTimeImmutable $at): void;

    /**
     * Increments `consecutive_failures`, stores the error message and
     * returns the new status (so the caller can decide on alert-mail
     * idempotency).
     */
    public function recordFailure(string $errorMessage): DhlCatalogSyncStatus;

    public function markAlertMailSent(): void;
}
