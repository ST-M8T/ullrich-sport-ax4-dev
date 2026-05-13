<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dhl\Catalog\Repositories;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlCatalogSyncStatus;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlCatalogSyncStatusRepository;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlCatalogSyncStatusModel;
use DateTimeImmutable;

/**
 * Eloquent implementation of the single-row sync-status repository.
 *
 * Engineering-Handbuch §11: methods are named for their fachliche
 * Bedeutung (recordAttempt / recordSuccess / recordFailure), not for
 * the underlying SQL operation.
 */
final class EloquentDhlCatalogSyncStatusRepository implements DhlCatalogSyncStatusRepository
{
    private const ROW_ID = 'current';

    public function get(): DhlCatalogSyncStatus
    {
        $model = $this->loadOrCreate();

        return $this->toDomain($model);
    }

    public function recordAttempt(DateTimeImmutable $at): void
    {
        $model = $this->loadOrCreate();
        $model->last_attempt_at = $at;
        $model->save();
    }

    public function recordSuccess(DateTimeImmutable $at): void
    {
        $model = $this->loadOrCreate();
        $model->last_success_at = $at;
        $model->consecutive_failures = 0;
        $model->mail_sent_for_failure_streak = false;
        $model->last_error = null;
        $model->save();
    }

    public function recordFailure(string $errorMessage): DhlCatalogSyncStatus
    {
        $model = $this->loadOrCreate();
        $model->consecutive_failures = (int) $model->consecutive_failures + 1;
        // §30: truncate to avoid runaway stacktraces in the status row.
        $model->last_error = mb_substr($errorMessage, 0, 4_000);
        $model->save();

        return $this->toDomain($model);
    }

    public function markAlertMailSent(): void
    {
        $model = $this->loadOrCreate();
        $model->mail_sent_for_failure_streak = true;
        $model->save();
    }

    private function loadOrCreate(): DhlCatalogSyncStatusModel
    {
        $model = DhlCatalogSyncStatusModel::query()->whereKey(self::ROW_ID)->first();
        if ($model === null) {
            $model = new DhlCatalogSyncStatusModel;
            $model->id = self::ROW_ID;
            $model->consecutive_failures = 0;
            $model->mail_sent_for_failure_streak = false;
            $model->save();
        }

        return $model;
    }

    private function toDomain(DhlCatalogSyncStatusModel $m): DhlCatalogSyncStatus
    {
        $lastAttempt = $m->last_attempt_at;
        $lastSuccess = $m->last_success_at;

        return new DhlCatalogSyncStatus(
            lastAttemptAt: $lastAttempt instanceof DateTimeImmutable
                ? $lastAttempt
                : ($lastAttempt !== null ? new DateTimeImmutable((string) $lastAttempt) : null),
            lastSuccessAt: $lastSuccess instanceof DateTimeImmutable
                ? $lastSuccess
                : ($lastSuccess !== null ? new DateTimeImmutable((string) $lastSuccess) : null),
            lastError: $m->last_error !== null ? (string) $m->last_error : null,
            consecutiveFailures: (int) $m->consecutive_failures,
            mailSentForFailureStreak: (bool) $m->mail_sent_for_failure_streak,
        );
    }
}
