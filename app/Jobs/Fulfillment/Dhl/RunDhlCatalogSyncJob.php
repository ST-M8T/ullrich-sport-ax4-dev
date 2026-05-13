<?php

declare(strict_types=1);

namespace App\Jobs\Fulfillment\Dhl;

use App\Application\Fulfillment\Integrations\Dhl\Catalog\SynchroniseDhlCatalogCommand;
use App\Application\Fulfillment\Integrations\Dhl\Catalog\SynchroniseDhlCatalogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * Queue job that runs a full DHL catalog sync asynchronously.
 *
 * Engineering-Handbuch §25: enthält keine Fachlogik — delegiert nur an
 * den Use-Case-Service. Idempotent via WithoutOverlapping-Middleware
 * (Engineering-Handbuch §24).
 */
final class RunDhlCatalogSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly string $actor,
    ) {}

    /**
     * @return list<\Illuminate\Queue\Middleware\WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('dhl-catalog-sync'))->expireAfter(600)];
    }

    public function handle(SynchroniseDhlCatalogService $service): void
    {
        $service->execute(new SynchroniseDhlCatalogCommand(
            actor: $this->actor,
        ));
    }
}
