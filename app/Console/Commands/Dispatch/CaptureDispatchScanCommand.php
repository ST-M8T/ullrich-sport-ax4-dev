<?php

declare(strict_types=1);

namespace App\Console\Commands\Dispatch;

use App\Application\Dispatch\DispatchListService;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use Illuminate\Console\Command;

class CaptureDispatchScanCommand extends Command
{
    protected $signature = 'dispatch:scan
        {list : Dispatch list ID}
        {barcode : Scan barcode}
        {--order-id= : Optional shipment order ID}
        {--user-id= : Optional user ID}
        {--captured-at= : Optional ISO-8601 timestamp}
        {--metadata= : JSON metadata}';

    protected $description = 'Capture a scan for a dispatch list via backend service';

    public function __construct(private readonly DispatchListService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $listId = Identifier::fromInt((int) $this->argument('list'));
        $barcode = (string) $this->argument('barcode');
        $orderIdOption = $this->option('order-id');
        $userIdOption = $this->option('user-id');
        $capturedAtOption = $this->option('captured-at');
        $metadataOption = $this->option('metadata');

        $orderId = null;
        if (is_string($orderIdOption) && $orderIdOption !== '') {
            $orderId = Identifier::fromInt((int) $orderIdOption);
        }

        $userId = null;
        if (is_string($userIdOption) && $userIdOption !== '') {
            $userId = Identifier::fromInt((int) $userIdOption);
        }

        $capturedAt = null;
        if (is_string($capturedAtOption) && $capturedAtOption !== '') {
            try {
                $capturedAt = new DateTimeImmutable($capturedAtOption);
            } catch (\Throwable $e) {
                $this->error('Invalid captured-at timestamp: '.$e->getMessage());

                return self::FAILURE;
            }
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

        try {
            $scan = $this->service->captureScan($listId, $barcode, $orderId, $userId, $capturedAt, $metadata);
            $this->info(sprintf('Captured scan #%d for list %d.', $scan->id()->toInt(), $listId->toInt()));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
