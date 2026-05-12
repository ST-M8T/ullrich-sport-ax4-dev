<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Services;

use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlBookingOptions;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Shared\ValueObjects\Identifier;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Handles bulk DHL shipment booking for multiple orders.
 * Each order is booked within its own database transaction.
 * When more than 10 orders are provided, processing is delegated to a queue job.
 */
final class DhlBulkBookingService
{
    private const SYNC_THRESHOLD = 10;

    public function __construct(
        private readonly DhlShipmentBookingService $bookingService,
        private readonly ShipmentOrderRepository $orderRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Book DHL shipments for multiple orders.
     *
     * @param  array<int>  $orderIds
     * @return array{results: array<int,array{orderId:int,success:bool,shipmentId?:string,trackingNumbers?:array<int,string>,error?:string}>, total:int, succeeded:int, failed:int, queued:bool}
     */
    public function bookBulk(
        array $orderIds,
        string $productId,
        array $additionalServices,
        ?string $pickupDate,
        ?string $payerCode = null,
        ?string $defaultPackageType = null,
    ): array {
        if ($orderIds === []) {
            return $this->emptyResult();
        }

        // Build options once and forward to both sync and queued paths
        // (DRY). product_code is omitted intentionally — fromArray() upgrades
        // legacy product_id to a typed DhlProductCode when it fits the spec
        // (≤ 3 alnum uppercase chars) and stays null otherwise, preserving
        // backward compatibility for callers that still pass long legacy ids.
        $options = DhlBookingOptions::fromArray([
            'product_id' => $productId,
            'additional_services' => $additionalServices,
            'pickup_date' => $pickupDate,
            'payer_code' => $payerCode,
            'default_package_type' => $defaultPackageType,
        ]);

        if (count($orderIds) > self::SYNC_THRESHOLD) {
            $this->logger->info('Bulk booking delegated to queue', [
                'order_count' => count($orderIds),
                'threshold' => self::SYNC_THRESHOLD,
            ]);

            return $this->queuedResult($orderIds, $options);
        }

        return $this->processSynchronously($orderIds, $options);
    }

    /**
     * @param  array<int>  $orderIds
     * @return array{results: array<int,array{orderId:int,success:bool,shipmentId?:string,trackingNumbers?:array<int,string>,error?:string}>, total:int, succeeded:int, failed:int, queued:bool}
     */
    private function processSynchronously(array $orderIds, DhlBookingOptions $options): array
    {
        $results = [];
        $succeeded = 0;
        $failed = 0;

        foreach ($orderIds as $orderId) {
            $result = $this->bookSingle(Identifier::fromInt($orderId), $options);

            $results[] = $result;

            if ($result['success']) {
                $succeeded++;
            } else {
                $failed++;
            }
        }

        return [
            'results' => $results,
            'total' => count($orderIds),
            'succeeded' => $succeeded,
            'failed' => $failed,
            'queued' => false,
        ];
    }

    /**
     * @return array{orderId:int,success:bool,shipmentId?:string,trackingNumbers?:array<int,string>,error?:string}
     */
    private function bookSingle(Identifier $orderId, DhlBookingOptions $options): array
    {
        try {
            $result = DB::transaction(function () use ($orderId, $options): DhlBookingResult {
                return ($this->bookingService)->bookShipment($orderId, $options);
            });

            if ($result->success) {
                $this->logger->info('Bulk DHL booking success', [
                    'order_id' => $orderId->toInt(),
                    'shipment_id' => $result->shipmentId,
                ]);

                return [
                    'orderId' => $orderId->toInt(),
                    'success' => true,
                    'shipmentId' => $result->shipmentId,
                    'trackingNumbers' => $result->trackingNumbers,
                ];
            }

            $this->logger->warning('Bulk DHL booking failed', [
                'order_id' => $orderId->toInt(),
                'error' => $result->error,
            ]);

            return [
                'orderId' => $orderId->toInt(),
                'success' => false,
                'error' => $result->error ?? 'Booking failed',
            ];
        } catch (Throwable $exception) {
            $this->logger->error('Bulk DHL booking exception', [
                'order_id' => $orderId->toInt(),
                'exception' => $exception->getMessage(),
            ]);

            return [
                'orderId' => $orderId->toInt(),
                'success' => false,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function emptyResult(): array
    {
        return [
            'results' => [],
            'total' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'queued' => false,
        ];
    }

    /**
     * @param  array<int>  $orderIds
     */
    private function queuedResult(array $orderIds, DhlBookingOptions $options): array
    {
        $job = new \App\Jobs\ProcessDhlBulkBookingJob($orderIds, $options);
        dispatch($job);

        return [
            'results' => [],
            'total' => count($orderIds),
            'succeeded' => 0,
            'failed' => 0,
            'queued' => true,
            'message' => 'Bulk booking has been queued for processing.',
        ];
    }
}
