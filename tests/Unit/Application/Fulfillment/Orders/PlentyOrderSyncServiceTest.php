<?php

namespace Tests\Unit\Application\Fulfillment\Orders;

use App\Application\Fulfillment\Orders\Packaging\OrderPackageCalculator;
use App\Application\Fulfillment\Orders\PlentyOrderSyncService;
use App\Application\Monitoring\AuditLogger;
use App\Application\Monitoring\DomainEventService;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Integrations\Contracts\PlentyOrderGateway;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class PlentyOrderSyncServiceTest extends TestCase
{
    private PlentyOrderGateway&MockInterface $gateway;

    private ShipmentOrderRepository&MockInterface $orders;

    private DomainEventService&MockInterface $events;

    private AuditLogger&MockInterface $audit;

    private OrderPackageCalculator&MockInterface $packageCalculator;

    private PlentyOrderSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = Mockery::mock(PlentyOrderGateway::class);
        $this->orders = Mockery::mock(ShipmentOrderRepository::class);
        $this->events = Mockery::mock(DomainEventService::class);
        $this->audit = Mockery::mock(AuditLogger::class);
        $this->packageCalculator = Mockery::mock(OrderPackageCalculator::class);
        // Default: kein Variation-Profil → Calculator liefert leeres Array,
        // Sync-Service ändert die Pakete nicht.
        $this->packageCalculator->shouldReceive('calculate')->andReturn([])->byDefault();

        $this->service = new PlentyOrderSyncService(
            $this->gateway,
            $this->orders,
            $this->events,
            $this->audit,
            $this->packageCalculator,
        );
    }

    public function test_sync_by_status_creates_new_orders_and_records_audit_trail(): void
    {
        $payload = [
            'orders' => [
                $this->plentyOrderPayload(120),
            ],
        ];

        $this->gateway
            ->shouldReceive('fetchOrdersByStatus')
            ->once()
            ->with(['6.0'], ['channel' => 'web'])
            ->andReturn($payload);

        $this->orders
            ->shouldReceive('getByExternalOrderId')
            ->once()
            ->with(120)
            ->andReturnNull();

        $this->orders
            ->shouldReceive('nextIdentity')
            ->once()
            ->andReturn(Identifier::fromInt(500));

        $this->orders
            ->shouldReceive('save')
            ->once()
            ->withArgs(function (ShipmentOrder $order): bool {
                $this->assertSame(120, $order->externalOrderId());
                $this->assertSame(44, $order->customerNumber());
                $this->assertSame('SenderCo', $order->senderCode());
                $this->assertSame('customer@example.test', $order->contactEmail());
                $this->assertSame('EUR', $order->currency());
                $this->assertSame(120.5, $order->totalAmount());
                $this->assertTrue($order->isBooked());
                $this->assertSame('plenty', $order->metadata()['plenty']['source']);

                return true;
            });

        $this->events
            ->shouldReceive('record')
            ->once()
            ->with(
                'fulfillment.shipment_order.synced',
                'shipment_order',
                Mockery::type('string'),
                Mockery::subset([
                    'external_order_id' => 120,
                    'is_update' => false,
                ]),
                Mockery::subset(['source' => 'plenty'])
            );

        $this->audit
            ->shouldReceive('log')
            ->once()
            ->with(
                'shipment_order.synced',
                'system',
                null,
                null,
                Mockery::subset([
                    'order_id' => 500,
                    'external_order_id' => 120,
                    'is_update' => false,
                ])
            );

        $count = $this->service->syncByStatus(['6.0'], ['channel' => 'web']);

        $this->assertSame(1, $count);
    }

    public function test_sync_by_status_updates_existing_orders_sets_update_flag(): void
    {
        $payload = $this->plentyOrderPayload(300);
        $existing = $this->shipmentOrder(Identifier::fromInt(910), 300, 'manual');

        $this->gateway
            ->shouldReceive('fetchOrdersByStatus')
            ->once()
            ->with(['6.0'], [])
            ->andReturn(['orders' => [$payload]]);

        $this->orders
            ->shouldReceive('getByExternalOrderId')
            ->once()
            ->with(300)
            ->andReturn($existing);

        $this->orders
            ->shouldReceive('save')
            ->once()
            ->withArgs(function (ShipmentOrder $order): bool {
                $this->assertSame(300, $order->externalOrderId());
                $this->assertSame('plenty', $order->metadata()['plenty']['source']);

                return true;
            });

        $this->events
            ->shouldReceive('record')
            ->once()
            ->with(
                'fulfillment.shipment_order.synced',
                'shipment_order',
                (string) $existing->id()->toInt(),
                Mockery::subset(['is_update' => true]),
                Mockery::subset(['source' => 'plenty'])
            );

        $this->audit
            ->shouldReceive('log')
            ->once()
            ->with(
                'shipment_order.synced',
                'system',
                null,
                null,
                Mockery::subset([
                    'order_id' => $existing->id()->toInt(),
                    'is_update' => true,
                ])
            );

        $count = $this->service->syncByStatus(['6.0']);

        $this->assertSame(1, $count);
    }

    private function plentyOrderPayload(int $orderId): array
    {
        return [
            'id' => $orderId,
            'billingAddress' => [
                'contactId' => 44,
            ],
            'plentyId' => 1234,
            'type' => 'order',
            'sender' => ['company' => 'SenderCo'],
            'receiver' => [
                'email' => 'customer@example.test',
                'phone' => '12345',
                'country' => 'DE',
            ],
            'currency' => 'EUR',
            'amounts' => [
                ['grossTotal' => 120.5],
            ],
            'processedAt' => '2024-01-01T12:00:00+00:00',
            'bookedAt' => '2024-01-02T12:00:00+00:00',
            'status' => 'BOOKED',
            'bookedBy' => 'System',
            'shippedAt' => '2024-01-03T12:00:00+00:00',
            'createdAt' => '2023-12-31T12:00:00+00:00',
            'updatedAt' => '2024-01-04T12:00:00+00:00',
            'orderItems' => [
                [
                    'id' => 900,
                    'itemId' => 10,
                    'variationId' => 11,
                    'sku' => 'SKU-1',
                    'text' => 'Item 1',
                    'quantity' => 2,
                ],
            ],
            'source' => 'plenty',
        ];
    }

    private function shipmentOrder(Identifier $id, int $externalId, string $senderCode): ShipmentOrder
    {
        $now = new DateTimeImmutable('-1 day');

        return ShipmentOrder::hydrate(
            $id,
            $externalId,
            null,
            null,
            'order',
            null,
            $senderCode,
            'old@example.test',
            null,
            'DE',
            'EUR',
            99.5,
            $now,
            true,
            $now,
            'Admin',
            $now,
            null,
            [],
            [],
            [],
            ['plenty' => ['source' => 'import']],
            $now,
            $now,
        );
    }
}
