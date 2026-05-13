<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Application\Fulfillment\Integrations\Dhl\Catalog\SynchroniseDhlCatalogCommand as SyncCommand;
use App\Application\Fulfillment\Integrations\Dhl\Catalog\SynchroniseDhlCatalogResult;
use App\Application\Fulfillment\Integrations\Dhl\Catalog\SynchroniseDhlCatalogService;
use App\Console\Commands\Dhl\Catalog\SyncDhlCatalogCommand;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @covers \App\Console\Commands\Dhl\Catalog\SyncDhlCatalogCommand
 */
final class SyncDhlCatalogCommandTest extends TestCase
{
    #[Test]
    public function success_path_returns_zero_and_renders_counts(): void
    {
        $this->bindServiceStub(static fn (SyncCommand $c): SynchroniseDhlCatalogResult => new SynchroniseDhlCatalogResult(
            productsAdded: 2,
            productsUpdated: 1,
            servicesAdded: 3,
            assignmentsAdded: 4,
            durationMs: 123,
        ));

        $this->artisan('dhl:catalog:sync')
            ->expectsOutputToContain('Products')
            ->expectsOutputToContain('Services')
            ->expectsOutputToContain('Assignments')
            ->expectsOutputToContain('OK')
            ->assertExitCode(0);
    }

    #[Test]
    public function error_path_returns_one_and_lists_errors(): void
    {
        $this->bindServiceStub(static fn (SyncCommand $c): SynchroniseDhlCatalogResult => new SynchroniseDhlCatalogResult(
            errors: [
                ['code' => 'phaseFailed', 'message' => 'something exploded'],
            ],
            durationMs: 50,
        ));

        $this->artisan('dhl:catalog:sync')
            ->expectsOutputToContain('phaseFailed')
            ->assertExitCode(1);
    }

    #[Test]
    public function suspicious_shrinkage_returns_exit_code_two(): void
    {
        $this->bindServiceStub(static fn (SyncCommand $c): SynchroniseDhlCatalogResult => new SynchroniseDhlCatalogResult(
            errors: [['code' => 'suspiciousShrinkage', 'message' => 'shrinkage']],
            durationMs: 10,
            suspicious: true,
        ));

        $this->artisan('dhl:catalog:sync')
            ->expectsOutputToContain('Suspicious shrinkage')
            ->assertExitCode(SyncDhlCatalogCommand::EXIT_SUSPICIOUS);
    }

    #[Test]
    public function dry_run_flag_is_forwarded_to_service(): void
    {
        $captured = null;
        $this->bindServiceStub(function (SyncCommand $c) use (&$captured): SynchroniseDhlCatalogResult {
            $captured = $c;

            return new SynchroniseDhlCatalogResult(dryRun: true, durationMs: 5);
        });

        $this->artisan('dhl:catalog:sync', ['--dry-run' => true])
            ->expectsOutputToContain('[DRY-RUN]')
            ->expectsOutputToContain('dry-run, no changes persisted')
            ->assertExitCode(0);

        $this->assertNotNull($captured);
        $this->assertTrue($captured->dryRun);
    }

    #[Test]
    public function routing_filter_is_forwarded_to_service(): void
    {
        $captured = null;
        $this->bindServiceStub(function (SyncCommand $c) use (&$captured): SynchroniseDhlCatalogResult {
            $captured = $c;

            return new SynchroniseDhlCatalogResult(durationMs: 5);
        });

        $this->artisan('dhl:catalog:sync', ['--routing' => 'DE-AT'])
            ->assertExitCode(0);

        $this->assertNotNull($captured);
        $this->assertSame('DE-AT', $captured->routingFilter);
    }

    #[Test]
    public function actor_option_overrides_default(): void
    {
        $captured = null;
        $this->bindServiceStub(function (SyncCommand $c) use (&$captured): SynchroniseDhlCatalogResult {
            $captured = $c;

            return new SynchroniseDhlCatalogResult(durationMs: 5);
        });

        $this->artisan('dhl:catalog:sync', ['--actor' => 'user:42'])
            ->assertExitCode(0);

        $this->assertNotNull($captured);
        $this->assertSame('user:42', $captured->actor);
    }

    #[Test]
    public function default_actor_is_used_when_option_omitted(): void
    {
        $captured = null;
        $this->bindServiceStub(function (SyncCommand $c) use (&$captured): SynchroniseDhlCatalogResult {
            $captured = $c;

            return new SynchroniseDhlCatalogResult(durationMs: 5);
        });

        $this->artisan('dhl:catalog:sync')
            ->assertExitCode(0);

        $this->assertNotNull($captured);
        $this->assertSame(SynchroniseDhlCatalogService::ACTOR_DEFAULT, $captured->actor);
        $this->assertNull($captured->routingFilter);
        $this->assertFalse($captured->dryRun);
    }

    #[Test]
    public function scheduler_registers_sync_command_when_cron_is_set(): void
    {
        config()->set('dhl-catalog.schedule_cron', '0 3 * * 0');
        // Reload console routes to apply config:
        require base_path('routes/console.php');

        $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
        $matching = array_filter(
            $schedule->events(),
            static fn ($event): bool => str_contains((string) $event->command, 'dhl:catalog:sync'),
        );

        $this->assertNotEmpty($matching, 'dhl:catalog:sync schedule entry must be registered.');
        $event = array_values($matching)[0];
        $this->assertSame('0 3 * * 0', $event->expression);
    }

    /**
     * @param  callable(SyncCommand):SynchroniseDhlCatalogResult  $handler
     */
    private function bindServiceStub(callable $handler): void
    {
        $stub = new class($handler) extends SynchroniseDhlCatalogService
        {
            /** @param callable(SyncCommand):SynchroniseDhlCatalogResult $handler */
            public function __construct(private $handler)
            {
                // intentionally do not call parent ctor — no dependencies needed for the override.
            }

            public function execute(SyncCommand $cmd): SynchroniseDhlCatalogResult
            {
                return ($this->handler)($cmd);
            }
        };

        $this->app->instance(SynchroniseDhlCatalogService::class, $stub);
    }
}
