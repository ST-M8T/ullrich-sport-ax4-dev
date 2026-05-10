<?php

namespace App\Console\Commands\Tracking;

use App\Application\Tracking\TrackingAlertService;
use App\Domain\Shared\ValueObjects\Identifier;
use Illuminate\Console\Command;

class RaiseTrackingAlertCommand extends Command
{
    protected $signature = 'tracking:alerts:raise
        {type : Alert type}
        {severity : Severity level}
        {message : Alert message}
        {--shipment-id= : Optional shipment identifier}
        {--channel= : Notification channel}
        {--metadata= : JSON metadata payload}';

    protected $description = 'Raise a tracking alert and persist it for later processing';

    public function __construct(private readonly TrackingAlertService $alerts)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $alertType = (string) $this->argument('type');
        $severity = (string) $this->argument('severity');
        $message = (string) $this->argument('message');
        $shipmentIdOption = $this->option('shipment-id');
        $channel = $this->option('channel');
        $metadataOption = $this->option('metadata');

        $shipmentId = null;
        if (is_string($shipmentIdOption) && $shipmentIdOption !== '') {
            $shipmentId = Identifier::fromInt((int) $shipmentIdOption);
        }

        $metadata = [];
        if (is_string($metadataOption) && $metadataOption !== '') {
            $decoded = json_decode($metadataOption, true);
            if (! is_array($decoded)) {
                $this->error('Metadata must be valid JSON.');

                return self::FAILURE;
            }
            $metadata = $decoded;
        }

        $alert = $this->alerts->raise(
            $alertType,
            $severity,
            $message,
            $shipmentId,
            is_string($channel) && $channel !== '' ? $channel : null,
            $metadata,
        );

        $this->info(sprintf('Tracking alert #%d created (%s / %s).', $alert->id()->toInt(), $alert->alertType(), $alert->severity()));

        return self::SUCCESS;
    }
}
