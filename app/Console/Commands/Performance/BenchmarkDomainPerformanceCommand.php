<?php

namespace App\Console\Commands\Performance;

use App\Application\Fulfillment\Masterdata\Queries\GetFulfillmentMasterdataCatalog;
use App\Application\Monitoring\Queries\ListSystemJobs;
use App\Domain\Monitoring\Contracts\SystemJobRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Benchmark;
use Illuminate\Support\Facades\Cache;

final class BenchmarkDomainPerformanceCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'performance:benchmark {--iterations=5 : Anzahl der Wiederholungen pro Messung}';

    /**
     * @var string
     */
    protected $description = 'Misst die Dauer zentraler Domain-Queries (Masterdata & Monitoring) im Kalt- und Warmstart.';

    public function handle(
        GetFulfillmentMasterdataCatalog $catalogQuery,
        SystemJobRepository $systemJobRepository,
        ListSystemJobs $listSystemJobs,
    ): int {
        $iterations = max(1, (int) $this->option('iterations'));

        // Masterdata benchmark – einmal kalt (Cache invalidiert), einmal warm.
        Cache::forget(config('performance.masterdata.cache_key', 'masterdata:catalog'));
        $masterdataCold = Benchmark::measure(
            fn () => $catalogQuery(),
            iterations: $iterations
        );

        $masterdataWarm = Benchmark::measure(
            fn () => $catalogQuery(),
            iterations: $iterations
        );

        // Monitoring benchmark – primär für aggregierte Queries.
        Cache::forget('monitoring:system-jobs:cache-version');
        $systemJobsCount = Benchmark::measure(
            fn () => $systemJobRepository->countByStatus(),
            iterations: $iterations
        );

        $systemJobsLatest = Benchmark::measure(
            fn () => $systemJobRepository->latest(10),
            iterations: $iterations
        );

        $systemJobsFirstPage = Benchmark::measure(
            fn () => $listSystemJobs([], config('performance.monitoring.page_size', 50), 1),
            iterations: $iterations
        );

        $this->newLine();
        $this->info(sprintf('Benchmark durchgeführt mit %d Wiederholungen pro Messpunkt.', $iterations));
        $this->table(
            ['Szenario', 'Durchschnitt (ms)'],
            [
                ['Masterdata (Kaltstart)', number_format($masterdataCold, 2)],
                ['Masterdata (Warm)', number_format($masterdataWarm, 2)],
                ['System Jobs Statuszählung', number_format($systemJobsCount, 2)],
                ['System Jobs Letzte 10', number_format($systemJobsLatest, 2)],
                ['System Jobs Seite 1', number_format($systemJobsFirstPage, 2)],
            ]
        );

        $this->comment('Hinweis: Werte sind Durchschnittszeiten pro Aufruf.');

        return Command::SUCCESS;
    }
}
