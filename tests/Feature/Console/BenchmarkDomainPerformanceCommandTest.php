<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Tests\TestCase;

final class BenchmarkDomainPerformanceCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_runs_benchmark_and_prints_results_table(): void
    {
        // Mit 1 Iteration für schnelle Tests.
        $exitCode = Artisan::call('performance:benchmark', ['--iterations' => 1]);

        self::assertSame(SymfonyCommand::SUCCESS, $exitCode);

        $output = Artisan::output();
        self::assertStringContainsString('Benchmark durchgeführt mit 1 Wiederholungen pro Messpunkt.', $output);
        self::assertStringContainsString('Masterdata (Kaltstart)', $output);
        self::assertStringContainsString('Masterdata (Warm)', $output);
        self::assertStringContainsString('System Jobs Statuszählung', $output);
        self::assertStringContainsString('System Jobs Letzte 10', $output);
        self::assertStringContainsString('System Jobs Seite 1', $output);
        self::assertStringContainsString('Hinweis: Werte sind Durchschnittszeiten pro Aufruf.', $output);
    }

    public function test_command_clamps_iterations_to_at_least_one(): void
    {
        $exitCode = Artisan::call('performance:benchmark', ['--iterations' => 0]);

        self::assertSame(SymfonyCommand::SUCCESS, $exitCode);
        // 0 wird via max(1, ...) auf 1 hochgezogen.
        self::assertStringContainsString('Benchmark durchgeführt mit 1 Wiederholungen pro Messpunkt.', Artisan::output());
    }
}
