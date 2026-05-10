<?php

namespace App\Console\Commands;

use App\Jobs\WarmDomainCaches as WarmDomainCachesJob;
use Illuminate\Console\Command;

final class WarmDomainCachesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'domain:cache:warm {--sync : Ausführung ohne Queue, direkt im CLI-Prozess}';

    /**
     * @var string
     */
    protected $description = 'Wärmt Masterdata- und Monitoring-Caches vor (Queue-gestützt).';

    public function handle(): int
    {
        if ($this->option('sync')) {
            WarmDomainCachesJob::dispatchSync();
            $this->info('Domain-Caches wurden synchron vorgewärmt.');

            return Command::SUCCESS;
        }

        WarmDomainCachesJob::dispatch();
        $this->info('WarmDomainCaches-Job wurde in die Queue gestellt.');

        return Command::SUCCESS;
    }
}
