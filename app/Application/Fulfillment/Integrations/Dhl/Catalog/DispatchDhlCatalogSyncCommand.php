<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Catalog;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlCatalogSyncStatus;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlCatalogSyncStatusRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\AuditActor;
use App\Jobs\Fulfillment\Dhl\RunDhlCatalogSyncJob;
use DateTimeImmutable;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;

/**
 * Application command: dispatch a manual catalog sync run.
 *
 * Engineering-Handbuch §5: orchestration only — kein Fachregelcode.
 *
 * Verantwortung:
 *   - Lock-Konflikt erkennen (Sync läuft, wenn `last_attempt_at` innerhalb der
 *     Lock-Fenster-Dauer liegt UND seither kein neuer last_success_at oder
 *     consecutive_failures-Wechsel passierte).
 *   - Bei Konflikt: ALREADY_RUNNING zurückgeben — keine zweite Dispatch.
 *   - Sonst: recordAttempt + Queue-Dispatch via Job.
 */
final class DispatchDhlCatalogSyncCommand
{
    public const RESULT_DISPATCHED = 'dispatched';
    public const RESULT_ALREADY_RUNNING = 'already_running';

    /**
     * Treat any attempt newer than this window as "still running" — matches
     * the `withoutOverlapping`-Lock TTL aus PROJ-2 Scheduler-Setup.
     */
    private const RUNNING_WINDOW_SECONDS = 60;

    public function __construct(
        private readonly DhlCatalogSyncStatusRepository $statusRepository,
        private readonly BusDispatcher $bus,
    ) {}

    /**
     * @return array{result:self::RESULT_*, status:DhlCatalogSyncStatus}
     */
    public function dispatchManual(AuditActor $actor): array
    {
        $status = $this->statusRepository->get();
        $now = new DateTimeImmutable;

        if ($this->isRunning($status, $now)) {
            return [
                'result' => self::RESULT_ALREADY_RUNNING,
                'status' => $status,
            ];
        }

        // Mark attempt immediately so concurrent triggers see the lock.
        $this->statusRepository->recordAttempt($now);

        $this->bus->dispatch(new RunDhlCatalogSyncJob(actor: $actor->value));

        $afterDispatchStatus = $this->statusRepository->get();

        return [
            'result' => self::RESULT_DISPATCHED,
            'status' => $afterDispatchStatus,
        ];
    }

    private function isRunning(DhlCatalogSyncStatus $status, DateTimeImmutable $now): bool
    {
        if ($status->lastAttemptAt === null) {
            return false;
        }
        // If success or failure was recorded after the attempt, the run finished.
        $finishedRef = $status->lastSuccessAt;
        if ($finishedRef !== null && $finishedRef >= $status->lastAttemptAt) {
            return false;
        }
        $age = $now->getTimestamp() - $status->lastAttemptAt->getTimestamp();

        return $age >= 0 && $age < self::RUNNING_WINDOW_SECONDS;
    }
}
