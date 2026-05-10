<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Jobs\WarmDomainCaches;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Tests\TestCase;

final class WarmDomainCachesCommandTest extends TestCase
{
    public function test_command_dispatches_warm_cache_job_to_queue_by_default(): void
    {
        Bus::fake();

        $exitCode = Artisan::call('domain:cache:warm');

        self::assertSame(SymfonyCommand::SUCCESS, $exitCode);
        self::assertStringContainsString('WarmDomainCaches-Job wurde in die Queue gestellt.', Artisan::output());
        Bus::assertDispatched(WarmDomainCaches::class);
    }

    public function test_command_runs_synchronously_when_sync_option_is_set(): void
    {
        Bus::fake();

        $exitCode = Artisan::call('domain:cache:warm', ['--sync' => true]);

        self::assertSame(SymfonyCommand::SUCCESS, $exitCode);
        self::assertStringContainsString('Domain-Caches wurden synchron vorgewärmt.', Artisan::output());
        Bus::assertDispatchedSync(WarmDomainCaches::class);
    }
}
