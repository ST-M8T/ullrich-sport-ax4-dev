<?php

namespace App\Console\Commands\Integrations;

use App\Application\Fulfillment\Shipments\DhlTrackingSyncService;
use Illuminate\Console\Command;

class DhlSyncTrackingCommand extends Command
{
    protected $signature = 'dhl:tracking:sync {tracking : Tracking number}';

    protected $description = 'Sync tracking events from DHL for a given tracking number';

    public function __construct(private readonly DhlTrackingSyncService $syncService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $trackingNumber = (string) $this->argument('tracking');
        $this->syncService->sync($trackingNumber);
        $this->info('Tracking synced for '.$trackingNumber);

        return self::SUCCESS;
    }
}
