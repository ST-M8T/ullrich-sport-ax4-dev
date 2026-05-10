<?php

namespace App\Console\Commands\Fulfillment;

use App\Application\Fulfillment\Orders\PlentyOrderSyncService;
use App\Domain\Fulfillment\Orders\ShipmentOrder;
use DateTimeImmutable;
use Illuminate\Console\Command;

class SyncOrdersCommand extends Command
{
    protected $signature = 'fulfillment:sync
        {--status=* : Plenty-Statuscodes für den Abgleich}
        {--from= : Zeitstempel (ISO8601) für processed_from}
        {--to= : Zeitstempel (ISO8601) für processed_to}
        {--channel=plenty : Kennzeichnung der Quell-Integration}';

    protected $description = 'Synchronisiert Fulfillment-Aufträge über das angebundene ERP.';

    public function __construct(private readonly PlentyOrderSyncService $syncService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        /** @var array<int, string> $statusCodes */
        $statusCodes = array_values(array_filter(
            (array) $this->option('status'),
            static fn ($code) => is_string($code) && $code !== ''
        ));
        if ($statusCodes === []) {
            $statusCodes = ['6.0'];
        }

        $filters = $this->buildFilters();

        $this->components->info(sprintf(
            'Synchronisiere Fulfillment-Aufträge (Status: %s, Quelle: %s)',
            implode(', ', $statusCodes),
            $this->option('channel') ?: 'plenty'
        ));

        $processed = 0;
        $created = 0;

        $count = $this->syncService->syncByStatus($statusCodes, $filters, function (ShipmentOrder $order, bool $wasUpdate) use (&$processed, &$created): void {
            $processed++;
            if (! $wasUpdate) {
                $created++;
            }

            $this->line(sprintf(
                ' #%d externe ID %d → %s',
                $order->id()->toInt(),
                $order->externalOrderId(),
                $wasUpdate ? 'aktualisiert' : 'neu'
            ));
        });

        $this->components->success(sprintf(
            '%d Aufträge verarbeitet (%d neu, %d aktualisiert).',
            $processed,
            $created,
            $processed - $created
        ));

        if ($count === 0) {
            $this->comment('Keine Aufträge für die angegebenen Filter gefunden.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildFilters(): array
    {
        $filters = [];

        $from = $this->option('from');
        $to = $this->option('to');

        if (is_string($from) && $from !== '') {
            $filters['processed_from'] = $this->parseDate($from);
        }

        if (is_string($to) && $to !== '') {
            $filters['processed_to'] = $this->parseDate($to);
        }

        $filters['channel'] = $this->option('channel') ?: 'plenty';

        return array_filter($filters, static fn ($value) => $value !== null);
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            $this->warn(sprintf('Ungültiges Datum ignoriert: %s', $value));

            return null;
        }
    }
}
