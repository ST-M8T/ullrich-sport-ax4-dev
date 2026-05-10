<?php

namespace App\Application\Fulfillment\Orders;

use App\Application\Monitoring\AuditLogger;
use App\Application\Monitoring\DomainEventService;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Fulfillment\Orders\ShipmentOrderItem;
use App\Domain\Integrations\Contracts\PlentyOrderGateway;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;

final class PlentyOrderSyncService
{
    public function __construct(
        private readonly PlentyOrderGateway $gateway,
        private readonly ShipmentOrderRepository $orders,
        private readonly DomainEventService $events,
        private readonly AuditLogger $auditLogger,
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
        $orderId = (int) ($orderData['id'] ?? 0);
        if ($orderId <= 0) {
            return null;
        }

        $existing = $this->orders->getByExternalOrderId($orderId);
        $identifier = $existing?->id() ?? $this->orders->nextIdentity();

        $items = [];
        foreach (($orderData['orderItems'] ?? []) as $item) {
            $itemId = (int) ($item['id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $items[] = ShipmentOrderItem::hydrate(
                Identifier::fromInt($itemId),
                $identifier,
                $item['itemId'] ?? null,
                $item['variationId'] ?? null,
                $item['sku'] ?? null,
                $item['text'] ?? null,
                (int) ($item['quantity'] ?? 1),
                null,
                null,
                false,
            );
        }

        $packages = $existing?->packages() ?? [];
        $trackingNumbers = $existing?->trackingNumbers() ?? [];

        $metadata = $existing?->metadata() ?? [];
        $metadata['plenty'] = $orderData;

        $processedAt = $existing?->processedAt() ?? $this->parseDate($orderData['processedAt'] ?? null);
        $bookedAt = $this->parseDate($orderData['bookedAt'] ?? null) ?? $existing?->bookedAt();
        $shippedAt = $this->parseDate($orderData['shippedAt'] ?? null) ?? $existing?->shippedAt();
        $createdAt = $existing?->createdAt() ?? $this->parseDate($orderData['createdAt'] ?? null) ?? new DateTimeImmutable;
        $updatedAt = $this->parseDate($orderData['updatedAt'] ?? null) ?? new DateTimeImmutable;

        $senderProfileId = $existing?->senderProfileId();
        $senderCode = $existing?->senderCode() ?? ($orderData['sender']['company'] ?? null);
        $contactEmail = $orderData['receiver']['email'] ?? $existing?->contactEmail();
        $contactPhone = $orderData['receiver']['phone'] ?? $existing?->contactPhone();
        $destinationCountry = $orderData['receiver']['country'] ?? $existing?->destinationCountry();
        $currency = $orderData['currency'] ?? $existing?->currency() ?? 'EUR';
        $totalAmount = isset($orderData['amounts'][0]['grossTotal'])
            ? (float) $orderData['amounts'][0]['grossTotal']
            : $existing?->totalAmount();

        $order = ShipmentOrder::hydrate(
            $identifier,
            $orderId,
            $orderData['contactId'] ?? null,
            $orderData['plentyId'] ?? null,
            $orderData['type'] ?? null,
            $senderProfileId,
            $senderCode,
            $contactEmail,
            $contactPhone,
            $destinationCountry,
            $currency,
            $totalAmount,
            $processedAt,
            ($orderData['status'] ?? '') === 'BOOKED',
            $bookedAt,
            $orderData['bookedBy'] ?? null,
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
                'status' => $orderData['status'] ?? null,
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

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        if (! $value) {
            return null;
        }

        return new DateTimeImmutable($value);
    }
}
