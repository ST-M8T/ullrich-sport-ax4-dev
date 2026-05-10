<?php

namespace App\Application\Fulfillment\Exports;

use App\Application\Monitoring\SystemJobLifecycleService;
use App\Domain\Fulfillment\Exports\ShipmentExportFilters;
use App\Domain\Fulfillment\Exports\ShipmentExportGenerator;
use App\Domain\Fulfillment\Exports\ShipmentExportResult;
use App\Domain\Monitoring\SystemJobEntry;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;

final class ShipmentExportManager
{
    public function __construct(
        private readonly ShipmentExportGenerator $generator,
        private readonly SystemJobLifecycleService $jobs,
        private readonly FilesystemFactory $filesystem,
    ) {}

    /**
     * @return array{
     *     job_id: int,
     *     file_path: string,
     *     file_size: int|null,
     *     orders_total: int
     * }
     */
    public function trigger(ShipmentExportFilters $filters, ?int $externalOrderId = null, ?int $userId = null): array
    {
        $payload = [
            'filters' => $filters->toArray(),
            'order_id' => $externalOrderId,
            'user_id' => $userId,
        ];

        $runContext = $userId !== null ? 'user:'.$userId : null;

        $job = $this->jobs->start('fulfillment.csv_export', 'shipment_orders', $runContext, $payload);

        try {
            $result = $this->generator->generate($filters, $externalOrderId);
            $fileMeta = $this->storeExport($result);

            $this->jobs->finish($job, 'completed', [
                'orders_total' => $result->orderCount(),
                'file' => $fileMeta['path'],
                'file_size' => $fileMeta['size'],
            ]);
        } catch (\Throwable $e) {
            $this->jobs->finish($job, 'failed', [
                'orders_total' => 0,
            ], $e->getMessage());
            throw $e;
        }

        return [
            'job_id' => $job->id(),
            'file_path' => $fileMeta['path'],
            'file_size' => $fileMeta['size'],
            'orders_total' => $result->orderCount(),
        ];
    }

    /**
     * @return array{
     *     job_id: int,
     *     file_path: string,
     *     file_size: int|null,
     *     orders_total: int
     * }
     */
    public function retryFromJob(SystemJobEntry $job, ?int $userId = null): array
    {
        if ($job->jobName() !== 'fulfillment.csv_export') {
            throw new \InvalidArgumentException('System job is not a CSV export job.');
        }

        $payload = $job->payload();
        $filters = ShipmentExportFilters::fromArray($payload['filters'] ?? []);
        $orderId = isset($payload['order_id']) && is_numeric($payload['order_id'])
            ? (int) $payload['order_id']
            : null;

        return $this->trigger($filters, $orderId, $userId);
    }

    /**
     * @return array{path: string, size: int|null}
     */
    private function storeExport(ShipmentExportResult $export): array
    {
        $disk = $this->filesystem->disk('local');
        $directory = 'exports';

        if (! $disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        $timestamp = now()->format('Ymd_His');
        $filename = sprintf('shipment_export_%s.csv', $timestamp);
        $path = $directory.'/'.$filename;

        $disk->put($path, $export->toCsvString());
        $size = $disk->size($path);

        return [
            'path' => $path,
            'size' => $size !== false ? (int) $size : null,
        ];
    }
}
