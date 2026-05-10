<?php

namespace App\Jobs;

use App\Application\Fulfillment\Masterdata\Queries\GetFulfillmentMasterdataCatalog;
use App\Application\Monitoring\Queries\ListSystemJobs;
use App\Domain\Monitoring\Contracts\SystemJobRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Warms cached domain queries to keep frequently accessed data fast.
 *
 * The job is idempotent and safe to run multiple times. By default it targets
 * the maintenance queue so it does not block business critical workers.
 */
final class WarmDomainCaches implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Timeout in Sekunden — Cache-Warming hat eigene Time-Bounds gegen lange Locks.
     */
    public int $timeout = 120;

    /**
     * Unique-Lock-Dauer (Sekunden). Verhindert parallele Warm-up-Läufe.
     */
    public int $uniqueFor = 300;

    public function __construct()
    {
        // Default-Queue für Wartungsläufe — überschreibbar per `performance.queue.maintenance_queue`.
        // `$queue` kommt vom Queueable-Trait (`?string $queue`); kein Re-Deklaration hier.
        $this->onQueue((string) config('performance.queue.maintenance_queue', 'maintenance'));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            // Keep the job unique for a short time to avoid duplicate warm-ups.
            new \Illuminate\Queue\Middleware\WithoutOverlapping('warm-domain-caches'),
        ];
    }

    public function handle(
        GetFulfillmentMasterdataCatalog $masterdataCatalog,
        SystemJobRepository $systemJobRepository,
        ListSystemJobs $listSystemJobs,
    ): void {
        // Masterdata cache is filled by calling the catalog query.
        $masterdataCatalog();

        // Prime aggregated monitoring caches.
        $systemJobRepository->countByStatus();
        $systemJobRepository->latest(10);

        // Warm the first page of the system job overview.
        $listSystemJobs([], config('performance.monitoring.page_size', 50), 1);
    }
}
