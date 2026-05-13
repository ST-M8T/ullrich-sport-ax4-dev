<?php

declare(strict_types=1);

namespace App\Console\Commands\Dhl\Catalog;

use App\Application\Fulfillment\Integrations\Dhl\Catalog\SynchroniseDhlCatalogCommand;
use App\Application\Fulfillment\Integrations\Dhl\Catalog\SynchroniseDhlCatalogResult;
use App\Application\Fulfillment\Integrations\Dhl\Catalog\SynchroniseDhlCatalogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CLI-Adapter (Presentation, Engineering-Handbuch §7) for Phase 3 of the
 * DHL catalog sync lifecycle (PROJ-2, t13).
 *
 * Translates CLI options into a {@see SynchroniseDhlCatalogCommand} DTO,
 * delegates orchestration to {@see SynchroniseDhlCatalogService}, and maps
 * the {@see SynchroniseDhlCatalogResult} to CLI output and an exit code.
 *
 * Engineering-Handbuch §7 / §26: thin adapter — NO business logic.
 *
 * Exit codes:
 *   0  success (no errors, not suspicious)
 *   1  errors recorded in result.errors
 *   2  suspicious shrinkage detected (hard-stop for CI / scheduler)
 */
final class SyncDhlCatalogCommand extends Command
{
    public const EXIT_SUSPICIOUS = 2;

    protected $signature = 'dhl:catalog:sync
        {--routing= : Routing filter "FROM-TO" (e.g. DE-AT). Default: all configured routings.}
        {--dry-run : Compute diff but roll back; no DB writes, no audit, no cache flush.}
        {--actor= : Audit actor identifier. Default: system:dhl-sync.}';

    protected $description = 'Synchronises the DHL catalog (products, services, assignments) from the live API.';

    public function handle(SynchroniseDhlCatalogService $service): int
    {
        $cmd = $this->buildCommand();
        $logger = Log::channel('dhl-catalog');

        $logger->info('dhl.catalog.sync.cli_started', [
            'routing' => $cmd->routingFilter,
            'dry_run' => $cmd->dryRun,
            'actor' => $cmd->actor,
        ]);

        try {
            $result = $service->execute($cmd);
        } catch (Throwable $e) {
            $this->error('Sync aborted: '.$e->getMessage());
            $logger->error('dhl.catalog.sync.cli_exception', [
                'message' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        $this->renderResult($result);

        $logger->info('dhl.catalog.sync.cli_completed', $result->toArray());

        if ($result->suspicious) {
            $this->error('Suspicious shrinkage detected — aborting (exit '.self::EXIT_SUSPICIOUS.').');

            return self::EXIT_SUSPICIOUS;
        }

        if ($result->hasErrors()) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function buildCommand(): SynchroniseDhlCatalogCommand
    {
        $routing = $this->option('routing');
        $routingFilter = is_string($routing) && $routing !== '' ? $routing : null;

        $actorOpt = $this->option('actor');
        $actor = is_string($actorOpt) && $actorOpt !== ''
            ? $actorOpt
            : SynchroniseDhlCatalogService::ACTOR_DEFAULT;

        return new SynchroniseDhlCatalogCommand(
            routingFilter: $routingFilter,
            dryRun: (bool) $this->option('dry-run'),
            actor: $actor,
        );
    }

    private function renderResult(SynchroniseDhlCatalogResult $result): void
    {
        $prefix = $result->dryRun ? '[DRY-RUN] ' : '';

        $this->table(
            [$prefix.'Entity', 'Added', 'Updated', 'Deprecated', 'Restored'],
            [
                ['Products', $result->productsAdded, $result->productsUpdated, $result->productsDeprecated, $result->productsRestored],
                ['Services', $result->servicesAdded, $result->servicesUpdated, $result->servicesDeprecated, $result->servicesRestored],
                ['Assignments', $result->assignmentsAdded, $result->assignmentsUpdated, $result->assignmentsDeprecated, '-'],
            ],
        );

        $this->line(sprintf('Duration: %.2fs', $result->durationMs / 1000));

        if ($result->hasErrors()) {
            $this->warn(sprintf('%d error(s):', count($result->errors)));
            foreach ($result->errors as $err) {
                $code = is_string($err['code'] ?? null) ? $err['code'] : 'unknown';
                $msg = is_string($err['message'] ?? null) ? $err['message'] : '';
                $this->line(sprintf('  - [%s] %s', $code, $msg));
            }

            return;
        }

        $this->info($result->dryRun ? 'OK (dry-run, no changes persisted)' : 'OK');
    }
}
