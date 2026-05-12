<?php

namespace App\Application\Fulfillment\Orders;

use App\Application\Monitoring\AuditLogger;
use App\Application\Monitoring\DomainEventService;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Integrations\Contracts\PlentyOrderGateway;
use DateTimeImmutable;
use InvalidArgumentException;

final class PlentyOrderSyncService
{
    public function __construct(
        private readonly PlentyOrderGateway $gateway,
        private readonly ShipmentOrderRepository $orders,
        private readonly DomainEventService $events,
        private readonly AuditLogger $auditLogger,
        private readonly PlentyOrderMapper $mapper = new PlentyOrderMapper(),
    ) {}

    /**
     * @param  array<int|string>  $statusCodes
     * @param  array<string,mixed>  $filters
     * @param  callable(ShipmentOrder,bool):void|null  $progress
     */
    public function syncByStatus(array $statusCodes, array $filters = [], ?callable $progress = null): int
    {
        $payload = $this->gateway->fetchOrdersByStatus($statusCodes, $filters);
        // Manche Gateways liefern den Auftragsblock direkt zurück, andere kapseln ihn
        // unter `orders`. Wir akzeptieren beide Formen und fallen sonst auf eine leere
        // Liste zurück, damit kein Sync läuft, wenn der Gateway nichts liefert.
        $orders = $payload['orders'] ?? $payload;
        if (! is_array($orders)) {
            $orders = [];
        }

        $synced = 0;

        foreach ($orders as $orderData) {
            $result = $this->persistOrderFromPayload($orderData);
            if ($result === null) {
                continue;
            }

            $synced++;

            if ($progress) {
                $progress($result['order'], $result['wasUpdate']);
            }
        }

        return $synced;
    }

    /**
     * Synchronisiert eine Menge konkreter Auftrags-IDs.
     *
     * @param  array<int,int|string>  $orderIds
     * @return array{
     *     requested:int,
     *     synced:int,
     *     created:int,
     *     updated:int,
     *     errors:array<int,string>
     * }
     */
    public function syncOrdersByIds(array $orderIds): array
    {
        $normalized = collect($orderIds)
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $summary = [
            'requested' => $normalized->count(),
            'synced' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        foreach ($normalized as $orderId) {
            try {
                $payload = $this->gateway->fetchOrder($orderId);
                if ($payload === null) {
                    $summary['errors'][$orderId] = 'Auftrag in Plenty nicht gefunden.';

                    continue;
                }

                $result = $this->persistOrderFromPayload($payload);
                if ($result === null) {
                    $summary['errors'][$orderId] = 'Ungültiges Auftrags-Payload.';

                    continue;
                }

                $summary['synced']++;
                if ($result['wasUpdate']) {
                    $summary['updated']++;
                } else {
                    $summary['created']++;
                }
            } catch (\Throwable $exception) {
                $summary['errors'][$orderId] = $exception->getMessage();
            }
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $orderData
     * @return array{order: ShipmentOrder, wasUpdate: bool}|null
     */
    private function persistOrderFromPayload(array $orderData): ?array
    {
        try {
            $mapped = $this->mapper->mapToOrderData($orderData);
        } catch (InvalidArgumentException) {
            return null;
        }

        $orderId = $mapped['externalOrderId'];

        $existing = $this->orders->getByExternalOrderId($orderId);
        $identifier = $existing?->id() ?? $this->orders->nextIdentity();

        $items = $this->mapper->mapItems($orderData, $identifier);

        $packages = $existing?->packages() ?? [];
        $trackingNumbers = $existing?->trackingNumbers() ?? [];

        $metadata = $existing?->metadata() ?? [];
        $metadata['plenty'] = $orderData;

        $processedAt = $existing?->processedAt() ?? $mapped['processedAt'];
        $bookedAt = $mapped['bookedAt'] ?? $existing?->bookedAt();
        $shippedAt = $mapped['shippedAt'] ?? $existing?->shippedAt();
        $createdAt = $existing?->createdAt() ?? $mapped['createdAt'] ?? new DateTimeImmutable;
        $updatedAt = $mapped['updatedAt'] ?? new DateTimeImmutable;

        $senderProfileId = $existing?->senderProfileId();
        $senderCode = $existing?->senderCode() ?? $mapped['senderCompany'];
        $contactEmail = $mapped['contactEmail'] ?? $existing?->contactEmail();
        $contactPhone = $mapped['contactPhone'] ?? $existing?->contactPhone();
        $destinationCountry = $mapped['destinationCountry'] ?? $existing?->destinationCountry();
        $currency = $mapped['currency'] ?? $existing?->currency() ?? 'EUR';
        $totalAmount = $mapped['totalAmount'] ?? $existing?->totalAmount();

        $order = ShipmentOrder::hydrate(
            $identifier,
            $orderId,
            $mapped['customerNumberAsInt'],
            $mapped['plentyId'],
            $mapped['type'],
            $senderProfileId,
            $senderCode,
            $contactEmail,
            $contactPhone,
            $destinationCountry,
            $currency,
            $totalAmount,
            $processedAt,
            $mapped['isBooked'],
            $bookedAt,
            $mapped['bookedBy'],
            $shippedAt,
            $existing?->lastExportFilename(),
            $items,
            $packages,
            $trackingNumbers,
            $metadata,
            $createdAt,
            $updatedAt,
        );

        $wasUpdate = $existing !== null;

        $this->orders->save($order);

        $this->events->record(
            'fulfillment.shipment_order.synced',
            'shipment_order',
            (string) $order->id()->toInt(),
            [
                'external_order_id' => $order->externalOrderId(),
                'status' => $mapped['status'],
                'synced_at' => (new DateTimeImmutable)->format(DATE_ATOM),
                'is_update' => $wasUpdate,
            ],
            [
                'currency' => $order->currency(),
                'total_amount' => $order->totalAmount(),
                'source' => 'plenty',
            ],
        );

        $this->auditLogger->log(
            'shipment_order.synced',
            'system',
            null,
            null,
            [
                'order_id' => $order->id()->toInt(),
                'external_order_id' => $order->externalOrderId(),
                'is_update' => $wasUpdate,
                'source' => 'plenty',
            ]
        );

        return [
            'order' => $order,
            'wasUpdate' => $wasUpdate,
        ];
    }
}
