<?php

declare(strict_types=1);

namespace App\Application\Dispatch;

use App\Application\Monitoring\AuditLogger;
use App\Application\Monitoring\DomainEventService;
use App\Domain\Dispatch\Contracts\DispatchListRepository;
use App\Domain\Dispatch\DispatchList;
use App\Domain\Dispatch\DispatchMetrics;
use App\Domain\Dispatch\DispatchScan;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use InvalidArgumentException;

final class DispatchListService
{
    public function __construct(
        private readonly DispatchListRepository $repository,
        private readonly DomainEventService $events,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @psalm-param array<string,mixed> $metadata
     */
    public function captureScan(
        Identifier $dispatchListId,
        string $barcode,
        ?Identifier $shipmentOrderId,
        ?Identifier $userId,
        ?DateTimeImmutable $capturedAt = null,
        array $metadata = []
    ): DispatchScan {
        $list = $this->repository->getById($dispatchListId);
        if (! $list) {
            throw new \RuntimeException('Dispatch list not found.');
        }

        if (! $list->canAddScans()) {
            throw new InvalidArgumentException('Cannot add scans to a closed dispatch list.');
        }

        $normalizedBarcode = trim($barcode);
        if ($normalizedBarcode === '') {
            throw new InvalidArgumentException('Barcode must be a non-empty string.');
        }

        $timestamp = new DateTimeImmutable;
        $effectiveCapturedAt = $capturedAt ?? $timestamp;

        $scan = DispatchScan::hydrate(
            $this->repository->nextScanIdentity(),
            $dispatchListId,
            $normalizedBarcode,
            $shipmentOrderId,
            $userId,
            $effectiveCapturedAt,
            $metadata,
            $timestamp,
            $timestamp,
        );

        $updatedList = $list->recordScan($scan, $timestamp);
        $persistedList = $this->repository->appendScan($updatedList, $scan);

        $this->events->record(
            'dispatch.list.scan_captured',
            'dispatch_list',
            (string) $dispatchListId->toInt(),
            [
                'barcode' => $scan->barcode(),
                'shipment_order_id' => $shipmentOrderId?->toInt(),
                'captured_at' => $scan->capturedAt()?->format(DATE_ATOM),
            ],
            [
                'user_id' => $userId?->toInt(),
            ],
        );

        $this->auditLogger->log(
            'dispatch.scan_captured',
            $userId ? 'user' : 'system',
            $userId ? (string) $userId->toInt() : null,
            null,
            [
                'dispatch_list_id' => $dispatchListId->toInt(),
                'barcode' => $scan->barcode(),
                'scan_count' => $persistedList->scanCount(),
            ]
        );

        return $scan;
    }

    public function get(Identifier $dispatchListId): ?DispatchList
    {
        return $this->repository->getById($dispatchListId);
    }

    public function updateMetrics(Identifier $dispatchListId, DispatchMetrics $metrics): void
    {
        $list = $this->repository->getById($dispatchListId);

        if (! $list) {
            throw new \RuntimeException('Dispatch list not found.');
        }

        $timestamp = new DateTimeImmutable;
        $updatedList = $list->withMetrics($metrics, $timestamp);

        $this->repository->save($updatedList);

        $this->events->record(
            'dispatch.list.metrics_updated',
            'dispatch_list',
            (string) $updatedList->id()->toInt(),
            [
                'total_orders' => $updatedList->metrics()->totalOrders(),
                'total_packages' => $updatedList->metrics()->totalPackages(),
                'total_items' => $updatedList->metrics()->totalItems(),
                'total_truck_slots' => $updatedList->metrics()->totalTruckSlots(),
                'calculated_at' => $updatedList->updatedAt()->format(DATE_ATOM),
            ],
            [
                'metrics' => $updatedList->metrics()->metrics(),
            ],
        );

        $this->auditLogger->log(
            'dispatch.metrics_updated',
            'system',
            null,
            null,
            [
                'dispatch_list_id' => $dispatchListId->toInt(),
                'total_orders' => $updatedList->metrics()->totalOrders(),
                'total_packages' => $updatedList->metrics()->totalPackages(),
                'total_items' => $updatedList->metrics()->totalItems(),
                'total_truck_slots' => $updatedList->metrics()->totalTruckSlots(),
                'calculated_at' => $updatedList->updatedAt()->format(DATE_ATOM),
            ]
        );
    }

    public function closeList(Identifier $dispatchListId, Identifier $userId, ?string $exportFile = null): DispatchList
    {
        $list = $this->repository->getById($dispatchListId);
        if (! $list) {
            throw new \RuntimeException('Dispatch list not found.');
        }

        if ($list->isClosed()) {
            return $list;
        }

        $closedAt = new DateTimeImmutable;
        $updated = $list->close($userId, $exportFile, $closedAt);

        $this->repository->save($updated);

        $this->events->record(
            'dispatch.list.closed',
            'dispatch_list',
            (string) $updated->id()->toInt(),
            [
                'closed_at' => $closedAt->format(DATE_ATOM),
                'export_filename' => $updated->exportFilename(),
            ],
            [
                'closed_by_user_id' => $userId->toInt(),
            ],
        );

        $this->auditLogger->log(
            'dispatch.list_closed',
            'user',
            (string) $userId->toInt(),
            null,
            [
                'dispatch_list_id' => $updated->id()->toInt(),
                'export_filename' => $updated->exportFilename(),
            ]
        );

        return $updated;
    }

    public function exportList(Identifier $dispatchListId, Identifier $userId, string $exportFilename): DispatchList
    {
        $list = $this->repository->getById($dispatchListId);
        if (! $list) {
            throw new \RuntimeException('Dispatch list not found.');
        }

        $exportFilename = trim($exportFilename);
        if ($exportFilename === '') {
            throw new InvalidArgumentException('Export filename is required.');
        }

        $exportedAt = new DateTimeImmutable;
        $updated = $list->export($userId, $exportFilename, $exportedAt);

        $this->repository->save($updated);

        $this->events->record(
            'dispatch.list.exported',
            'dispatch_list',
            (string) $updated->id()->toInt(),
            [
                'exported_at' => $exportedAt->format(DATE_ATOM),
                'export_filename' => $exportFilename,
            ],
            [
                'exported_by_user_id' => $userId->toInt(),
            ],
        );

        $this->auditLogger->log(
            'dispatch.list_exported',
            'user',
            (string) $userId->toInt(),
            null,
            [
                'dispatch_list_id' => $updated->id()->toInt(),
                'export_filename' => $exportFilename,
            ]
        );

        return $updated;
    }
}
