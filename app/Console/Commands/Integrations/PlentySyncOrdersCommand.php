<?php

namespace App\Console\Commands\Integrations;

use App\Application\Fulfillment\Orders\PlentyOrderSyncService;
use Illuminate\Console\Command;

class PlentySyncOrdersCommand extends Command
{
    protected $signature = 'plenty:orders:sync {--status=* : Status codes to sync}';

    protected $description = 'Sync orders from PlentyMarkets into the local fulfillment database';

    public function __construct(private readonly PlentyOrderSyncService $syncService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        /** @var array<int, string> $status */
        $status = array_values(array_filter(
            (array) $this->option('status'),
            static fn ($code) => is_string($code) && $code !== ''
        ));
        if ($status === []) {
            $status = ['6.0'];
        }

        $count = $this->syncService->syncByStatus($status);
        $this->info(sprintf('%d orders synced from Plenty.', $count));

        return self::SUCCESS;
    }
}
