<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Services;

use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlBookingOptions;
use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlBookingResponseDto;
use App\Application\Monitoring\AuditLogger;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentSenderProfileRepository;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Integrations\Contracts\DhlFreightGateway;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use Throwable;

final class DhlShipmentBookingService
{
    public function __construct(
        private readonly DhlFreightGateway $gateway,
        private readonly ShipmentOrderRepository $orderRepository,
        private readonly FulfillmentSenderProfileRepository $senderRepository,
        private readonly DhlPayloadMapper $mapper,
        private readonly AuditLogger $auditLogger,
        private readonly LoggerInterface $logger,
    ) {
        // Constructor is only used for dependency injection.
    }

    public function bookShipment(
        Identifier $orderId,
        DhlBookingOptions $options,
    ): DhlBookingResult {
        $order = $this->orderRepository->getById($orderId);
        if ($order === null) {
            throw new \RuntimeException('Shipment order not found.');
        }

        if ($order->senderProfileId() === null) {
            throw new \RuntimeException('Shipment order has no sender profile.');
        }

        $senderProfile = $this->senderRepository->getById($order->senderProfileId());
        if ($senderProfile === null) {
            throw new \RuntimeException('Sender profile not found.');
        }

        try {
            return DB::transaction(function () use ($order, $senderProfile, $options): DhlBookingResult {
                $payload = $this->mapper->mapToBookingPayload(
                    $order,
                    $senderProfile,
                    $options
                );

                $this->logger->info('DHL booking request', [
                    'order_id' => $order->id()->toInt(),
                    'product_id' => $options->productId(),
                ]);

                try {
                    $response = $this->gateway->bookShipment($payload);
                } catch (RequestException $requestException) {
                    // RequestException::$response ist non-nullable, aber `json()` kann
                    // `null` liefern, wenn der Body kein JSON ist.
                    $response = $requestException->response->json() ?? [];
                    $errorMessage = $requestException->getMessage();

                    $this->logger->error('DHL booking HTTP error', [
                        'order_id' => $order->id()->toInt(),
                        'status' => $requestException->response->status(),
                        'error' => $errorMessage,
                    ]);

                    $updated = $this->updateOrderWithBookingError($order, $payload, is_array($response) ? $response : [], $errorMessage);
                    $this->orderRepository->save($updated);

                    return new DhlBookingResult(false, null, [], $errorMessage);
                }

                $responseDto = new DhlBookingResponseDto($response);

                if ($responseDto->isSuccess() === false) {
                    $errorMessage = $responseDto->errorMessage() ?? 'Unknown error';
                    $this->logger->error('DHL booking failed', [
                        'order_id' => $order->id()->toInt(),
                        'error' => $errorMessage,
                        'response' => $response,
                    ]);

                    $updated = $this->updateOrderWithBookingError($order, $payload, $response, $errorMessage);
                    $this->orderRepository->save($updated);

                    return new DhlBookingResult(
                        success: false,
                        shipmentId: null,
                        trackingNumbers: [],
                        error: $errorMessage,
                    );
                }

                $shipmentId = $responseDto->shipmentId();
                $trackingNumbers = $responseDto->trackingNumbers();

                $this->logger->info('DHL booking successful', [
                    'order_id' => $order->id()->toInt(),
                    'shipment_id' => $shipmentId,
                    'tracking_numbers' => $trackingNumbers,
                ]);

                $updated = $this->updateOrderWithBookingSuccess(
                    $order,
                    $options->productId(),
                    $payload,
                    $response,
                    $shipmentId,
                    $trackingNumbers,
                );
                $this->orderRepository->save($updated);

                $this->auditLogger->log(
                    'fulfillment.dhl.shipment_booked',
                    'system',
                    null,
                    'dhl-booking-service',
                    [
                        'shipment_order_id' => $order->id()->toInt(),
                        'dhl_shipment_id' => $shipmentId,
                        'tracking_numbers' => $trackingNumbers,
                    ],
                );

                return new DhlBookingResult(
                    success: true,
                    shipmentId: $shipmentId,
                    trackingNumbers: $trackingNumbers,
                    error: null,
                );
            });
        } catch (Throwable $exception) {
            $this->logger->error('DHL booking exception', [
                'order_id' => $orderId->toInt(),
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $order = $this->orderRepository->getById($orderId);
            if ($order !== null) {
                $updated = $this->updateOrderWithBookingError(
                    $order,
                    [],
                    [],
                    $exception->getMessage(),
                );
                $this->orderRepository->save($updated);
            }

            throw new \RuntimeException('DHL booking failed: '.$exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $response
     * @param  array<int,string>  $trackingNumbers
     */
    private function updateOrderWithBookingSuccess(
        ShipmentOrder $order,
        ?string $productId,
        array $payload,
        array $response,
        ?string $shipmentId,
        array $trackingNumbers,
    ): ShipmentOrder {
        $now = new DateTimeImmutable;
        $allTrackingNumbers = array_unique(array_merge($order->trackingNumbers(), $trackingNumbers));

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
            true,
            $order->bookedAt() ?? $now,
            $order->bookedBy() ?? 'dhl-booking-service',
            $order->shippedAt(),
            $order->lastExportFilename(),
            $order->items(),
            $order->packages(),
            $allTrackingNumbers,
            $order->metadata(),
            $order->createdAt(),
            $now,
            $shipmentId,
            $order->dhlLabelUrl(),
            $order->dhlLabelPdfBase64(),
            $order->dhlPickupReference(),
            $productId,
            $payload,
            $response,
            null,
            $now,
        );
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $response
     */
    private function updateOrderWithBookingError(
        ShipmentOrder $order,
        array $payload,
        array $response,
        string $errorMessage,
    ): ShipmentOrder {
        $now = new DateTimeImmutable;

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
            $now,
            $order->dhlShipmentId(),
            $order->dhlLabelUrl(),
            $order->dhlLabelPdfBase64(),
            $order->dhlPickupReference(),
            $order->dhlProductId(),
            $payload,
            $response,
            $errorMessage,
            null,
        );
    }
}

final class DhlBookingResult
{
    /**
     * @param  array<int,string>  $trackingNumbers
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $shipmentId,
        public readonly array $trackingNumbers,
        public readonly ?string $error,
    ) {
        // Booking service result transport object.
    }
}
