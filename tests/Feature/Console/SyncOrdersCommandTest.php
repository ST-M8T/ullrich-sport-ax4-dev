<?php

namespace Tests\Feature\Console;

use App\Application\Fulfillment\Orders\PlentyOrderSyncService;
use App\Application\Monitoring\AuditLogger;
use App\Application\Monitoring\DomainEventService;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Integrations\Contracts\PlentyOrderGateway;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class SyncOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_orders_command_persists_orders(): void
    {
        $orders = [
            [
                'id' => 1200,
                'status' => 'BOOKED',
                'processedAt' => Carbon::now()->subDay()->toIso8601String(),
                'bookedAt' => Carbon::now()->subDay()->toIso8601String(),
                'shippedAt' => null,
                'createdAt' => Carbon::now()->subDays(2)->toIso8601String(),
                'updatedAt' => Carbon::now()->subDay()->toIso8601String(),
                'sender' => [
                    'company' => 'AX4',
                ],
                'receiver' => [
                    'email' => 'customer@example.com',
                    'phone' => '+491234567',
                    'country' => 'DE',
                ],
                'currency' => 'EUR',
                'amounts' => [
                    ['grossTotal' => 199.99],
                ],
                'orderItems' => [
                    [
                        'id' => 501,
                        'itemId' => 42,
                        'variationId' => 84,
                        'sku' => 'SKU-001',
                        'text' => 'Demo Item',
                        'quantity' => 1,
                    ],
                ],
            ],
        ];

        $gateway = new class($orders) implements PlentyOrderGateway
        {
            public array $statuses = [];

            public array $filters = [];

            public function __construct(private readonly array $orders) {}

            public function fetchOrdersByStatus(array $statusCodes, array $filters = []): array
            {
                $this->statuses = $statusCodes;
                $this->filters = $filters;

                return ['orders' => $this->orders];
            }

            public function fetchOrder(int $orderId): ?array
            {
                return null;
            }

            public function updateOrderStatus(int $orderId, string $statusCode): void {}

            public function ping(): array
            {
                return [
                    'status' => 200,
                    'duration_ms' => 0.0,
                    'body' => null,
                ];
            }
        };

        $this->app->instance(PlentyOrderGateway::class, $gateway);
        $this->app->bind(PlentyOrderGateway::class, fn () => $gateway);

        $this->app->singleton(PlentyOrderSyncService::class, function ($app) use ($gateway): PlentyOrderSyncService {
            return new PlentyOrderSyncService(
                $gateway,
                $app->make(ShipmentOrderRepository::class),
                $app->make(DomainEventService::class),
                $app->make(AuditLogger::class),
            );
        });

        $this->artisan('fulfillment:sync', ['--status' => ['6.0'], '--channel' => 'plenty'])
            ->assertExitCode(0);

        $this->assertSame(['6.0'], $gateway->statuses);
        $this->assertArrayHasKey('channel', $gateway->filters);
        $this->assertSame('plenty', $gateway->filters['channel']);

        $this->assertDatabaseHas('shipment_orders', [
            'external_order_id' => 1200,
        ]);

        $order = ShipmentOrderModel::where('external_order_id', 1200)->firstOrFail();
        $this->assertSame('customer@example.com', $order->contact_email);
        $this->assertSame('AX4', $order->sender_code);
    }
}
