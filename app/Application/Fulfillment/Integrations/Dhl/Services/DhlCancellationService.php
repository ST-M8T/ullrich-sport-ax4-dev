<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Services;

use App\Application\Fulfillment\Shipments\ShipmentTrackingService;
use App\Application\Monitoring\AuditLogger;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Integrations\Contracts\DhlFreightGateway;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

final class DhlCancellationService
{
    public function __construct(
        private readonly DhlFreightGateway $gateway,
        private readonly ShipmentOrderRepository $orderRepository,
        private readonly ShipmentTrackingService $trackingService,
        private readonly AuditLogger $auditLogger,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Cancel a DHL shipment for a given shipment order.
     *
     * Steps:
     * 1. Load shipment order and verify DHL shipment exists
     * 2. Call DHL gateway (local marking if no API cancel available)
     * 3. Mark shipment order as cancelled in DB
     * 4. Record cancellation events on all tracking numbers
     */
    public function cancel(int $shipmentOrderId, string $reason, string $cancelledBy): DhlCancellationResult
    {
        $orderId = Identifier::fromInt($shipmentOrderId);
        $order = $this->orderRepository->getById($orderId);

        if ($order === null) {
            return new DhlCancellationResult(
                success: false,
                dhlConfirmationNumber: null,
                cancelledAt: null,
                error: "Shipment order {$shipmentOrderId} not found.",
            );
        }

        $shipmentId = $order->dhlShipmentId();
        if ($shipmentId === null) {
            return new DhlCancellationResult(
                success: false,
                dhlConfirmationNumber: null,
                cancelledAt: null,
                error: "No DHL shipment ID found for order {$shipmentOrderId}.",
            );
        }

        if ($order->dhlCancelledAt() !== null) {
            return new DhlCancellationResult(
                success: false,
                dhlConfirmationNumber: null,
                cancelledAt: $order->dhlCancelledAt(),
                error: "Shipment is already cancelled.",
            );
        }

        try {
            // Call DHL gateway (local marking if no API cancel available)
            $gatewayResult = $this->gateway->cancelShipment($shipmentId, $reason);

            $cancelledAt = new DateTimeImmutable($gatewayResult['cancelled_at'] ?? 'now');
            $confirmationNumber = $gatewayResult['confirmation_number'] ?? null;
            $gatewayError = $gatewayResult['error'] ?? null;

            if ($gatewayError !== null || ($gatewayResult['success'] ?? false) === false) {
                $this->logger->warning('[DHL Cancellation] Gateway returned error', [
                    'order_id' => $shipmentOrderId,
                    'shipment_id' => $shipmentId,
                    'error' => $gatewayError,
                ]);

                return new DhlCancellationResult(
                    success: false,
                    dhlConfirmationNumber: null,
                    cancelledAt: null,
                    error: $gatewayError ?? 'DHL gateway cancellation failed.',
                );
            }

            // Mark order as cancelled
            $updatedOrder = $this->markOrderAsCancelled($order, $cancelledAt, $cancelledBy, $reason);
            $this->orderRepository->save($updatedOrder);

            // Record cancellation event on all tracking numbers
            $this->recordCancellationEvents($order->trackingNumbers(), $shipmentId, $reason, $cancelledBy, $cancelledAt);

            $this->auditLogger->log(
                'fulfillment.dhl.shipment_cancelled',
                $cancelledBy,
                null,
                'dhl-cancellation-service',
                [
                    'shipment_order_id' => $shipmentOrderId,
                    'dhl_shipment_id' => $shipmentId,
                    'reason' => $reason,
                    'cancelled_at' => $cancelledAt->format(DATE_ATOM),
                ],
            );

            $this->logger->info('[DHL Cancellation] Shipment cancelled successfully', [
                'order_id' => $shipmentOrderId,
                'shipment_id' => $shipmentId,
                'reason' => $reason,
                'cancelled_at' => $cancelledAt->format(DATE_ATOM),
            ]);

            return new DhlCancellationResult(
                success: true,
                dhlConfirmationNumber: $confirmationNumber,
                cancelledAt: $cancelledAt->format(DATE_ATOM),
                error: null,
            );
        } catch (\Throwable $exception) {
            $this->logger->error('[DHL Cancellation] Exception during cancellation', [
                'order_id' => $shipmentOrderId,
                'shipment_id' => $shipmentId,
                'exception' => $exception->getMessage(),
            ]);

            return new DhlCancellationResult(
                success: false,
                dhlConfirmationNumber: null,
                cancelledAt: null,
                error: $exception->getMessage(),
            );
        }
    }

    private function markOrderAsCancelled(
        ShipmentOrder $order,
        DateTimeImmutable $cancelledAt,
        string $cancelledBy,
        string $reason,
    ): ShipmentOrder {
        return ShipmentOrder::hydrate(
            $order->id(),
            $order->externalOrderId(),
            $order->customerNumber(),
            $order->plentyOrderId(),
            $order->orderType(),
            $order->senderProfileId(),
            $order->senderCode(),
            $order->contactEmail(),
            $order->contactPhone(),
            $order->destinationCountry(),
            $order->currency(),
            $order->totalAmount(),
            $order->processedAt(),
            $order->isBooked(),
            $order->bookedAt(),
            $order->bookedBy(),
            $order->shippedAt(),
            $order->lastExportFilename(),
            $order->items(),
            $order->packages(),
            $order->trackingNumbers(),
            $order->metadata(),
            $order->createdAt(),
            new DateTimeImmutable,
            $order->dhlShipmentId(),
            $order->dhlLabelUrl(),
            $order->dhlLabelPdfBase64(),
            $order->dhlPickupReference(),
            $order->dhlProductId(),
            $order->dhlBookingPayload(),
            $order->dhlBookingResponse(),
            $order->dhlBookingError(),
            $order->dhlBookedAt(),
            $cancelledAt->format('Y-m-d H:i:s'),
            $cancelledBy,
            $reason,
        );
    }

    private function recordCancellationEvents(
        array $trackingNumbers,
        string $shipmentId,
        string $reason,
        string $cancelledBy,
        DateTimeImmutable $cancelledAt,
    ): void {
        foreach ($trackingNumbers as $trackingNumber) {
            try {
                $this->trackingService->recordEvent(
                    $trackingNumber,
                    'CANCELLED',
                    'CANCELLED',
                    "Sendung storniert: {$reason}",
                    null,
                    null,
                    null,
                    $cancelledAt,
                    [
                        'dhl_shipment_id' => $shipmentId,
                        'reason' => $reason,
                        'cancelled_by' => $cancelledBy,
                        'cancelled_at' => $cancelledAt->format(DATE_ATOM),
                        'type' => 'dhl_cancellation',
                    ],
                );
            } catch (\Throwable $exception) {
                $this->logger->warning('[DHL Cancellation] Failed to record event for tracking', [
                    'tracking_number' => $trackingNumber,
                    'shipment_id' => $shipmentId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}