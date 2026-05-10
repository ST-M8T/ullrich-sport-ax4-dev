<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Services;

use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlLabelResponseDto;
use App\Application\Monitoring\AuditLogger;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Integrations\Contracts\DhlFreightGateway;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use Throwable;

final class DhlLabelService
{
    public function __construct(
        private readonly DhlFreightGateway $gateway,
        private readonly ShipmentOrderRepository $orderRepository,
        private readonly DhlPayloadMapper $mapper,
        private readonly AuditLogger $auditLogger,
        private readonly LoggerInterface $logger,
    ) {
        // Services are wired via the container; no setup logic required.
    }

    /**
     * @param  array<string,mixed>  $options
     */
    public function generateLabel(
        Identifier $orderId,
        array $options = [],
    ): DhlLabelResult {
        $order = $this->orderRepository->getById($orderId);
        if ($order === null) {
            throw new \RuntimeException('Shipment order not found.');
        }

        $shipmentId = $order->dhlShipmentId();
        if ($shipmentId === null) {
            throw new \RuntimeException('Shipment order has no DHL shipment ID. Book the shipment first.');
        }

        try {
            return DB::transaction(function () use ($order, $shipmentId, $options): DhlLabelResult {
                $requestPayload = $this->mapper->mapToLabelRequest($shipmentId, $options);

                $this->logger->info('DHL label request', [
                    'order_id' => $order->id()->toInt(),
                    'shipment_id' => $shipmentId,
                ]);

                $response = $this->gateway->printLabel($shipmentId, $requestPayload);
                $responseDto = new DhlLabelResponseDto($response);

                if ($responseDto->isSuccess() === false) {
                    $errorMessage = $responseDto->errorMessage() ?? 'Unknown error';
                    $this->logger->error('DHL label generation failed', [
                        'order_id' => $order->id()->toInt(),
                        'shipment_id' => $shipmentId,
                        'error' => $errorMessage,
                    ]);

                    return new DhlLabelResult(
                        success: false,
                        labelUrl: null,
                        labelPdfBase64: null,
                        error: $errorMessage,
                    );
                }

                $labelUrl = $responseDto->labelUrl();
                $labelPdfBase64 = $responseDto->labelPdfBase64();

                $this->logger->info('DHL label generated', [
                    'order_id' => $order->id()->toInt(),
                    'shipment_id' => $shipmentId,
                    'has_url' => $labelUrl !== null,
                    'has_pdf' => $labelPdfBase64 !== null,
                ]);

                $updated = $this->updateOrderWithLabel($order, $labelUrl, $labelPdfBase64);
                $this->orderRepository->save($updated);

                $this->auditLogger->log(
                    'fulfillment.dhl.label_generated',
                    'system',
                    null,
                    'dhl-label-service',
                    [
                        'shipment_order_id' => $order->id()->toInt(),
                        'dhl_shipment_id' => $shipmentId,
                        'has_url' => $labelUrl !== null,
                    ],
                );

                return new DhlLabelResult(
                    success: true,
                    labelUrl: $labelUrl,
                    labelPdfBase64: $labelPdfBase64,
                    error: null,
                );
            });
        } catch (Throwable $exception) {
            $this->logger->error('DHL label exception', [
                'order_id' => $orderId->toInt(),
                'exception' => $exception->getMessage(),
            ]);

            throw new \RuntimeException('DHL label generation failed: '.$exception->getMessage(), 0, $exception);
        }
    }

    public function downloadLabelAsPdf(Identifier $orderId): ?string
    {
        $order = $this->orderRepository->getById($orderId);
        if ($order === null) {
            throw new \RuntimeException('Shipment order not found.');
        }

        $labelPdfBase64 = $order->dhlLabelPdfBase64();
        if ($labelPdfBase64 !== null) {
            return $labelPdfBase64;
        }

        $labelUrl = $order->dhlLabelUrl();
        if ($labelUrl !== null) {
            try {
                /** @var \Illuminate\Http\Client\Response $response */
                $response = \Illuminate\Support\Facades\Http::get($labelUrl);
                if ($response->successful()) {
                    return base64_encode($response->body());
                }
            } catch (Throwable $exception) {
                $this->logger->warning('Failed to download label from URL', [
                    'order_id' => $orderId->toInt(),
                    'url' => $labelUrl,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $result = $this->generateLabel($orderId);
        if ($result->success && $result->labelPdfBase64 !== null) {
            return $result->labelPdfBase64;
        }

        return null;
    }

    private function updateOrderWithLabel(
        ShipmentOrder $order,
        ?string $labelUrl,
        ?string $labelPdfBase64,
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
            $labelUrl ?? $order->dhlLabelUrl(),
            $labelPdfBase64 ?? $order->dhlLabelPdfBase64(),
            $order->dhlPickupReference(),
            $order->dhlProductId(),
            $order->dhlBookingPayload(),
            $order->dhlBookingResponse(),
            $order->dhlBookingError(),
            $order->dhlBookedAt(),
        );
    }
}

final class DhlLabelResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $labelUrl,
        public readonly ?string $labelPdfBase64,
        public readonly ?string $error,
    ) {
        // DTO-style result wrapper.
    }
}
