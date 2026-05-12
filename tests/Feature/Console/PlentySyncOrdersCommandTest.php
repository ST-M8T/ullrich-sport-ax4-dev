<?php

namespace Tests\Feature\Console;

use App\Application\Fulfillment\Orders\PlentyOrderSyncService;
use App\Application\Monitoring\AuditLogger;
use App\Application\Monitoring\DomainEventService;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Integrations\Contracts\PlentyOrderGateway;
use Mockery;
use Tests\TestCase;

final class PlentySyncOrdersCommandTest extends TestCase
{
    public function test_sync_orders_command_uses_default_status(): void
    {
        $service = $this->bootService(['6.0']);
        $this->app->instance(PlentyOrderSyncService::class, $service);

        $this->artisan('plenty:orders:sync')
            ->expectsOutput('0 orders synced from Plenty.')
            ->assertExitCode(0);
    }

    public function test_sync_orders_command_accepts_custom_statuses(): void
    {
        $service = $this->bootService(['5.0', '7.0']);
        $this->app->instance(PlentyOrderSyncService::class, $service);

        $this->artisan('plenty:orders:sync', ['--status' => ['5.0', '7.0']])
            ->expectsOutput('0 orders synced from Plenty.')
            ->assertExitCode(0);
    }

    /**
     * @param  array<int,string>  $expectedStatuses
     */
    private function bootService(array $expectedStatuses): PlentyOrderSyncService
    {
        $gateway = Mockery::mock(PlentyOrderGateway::class);
        $gateway->shouldReceive('fetchOrdersByStatus')
            ->once()
            ->with($expectedStatuses, [])
            ->andReturn(['orders' => []]);

        $orders = Mockery::mock(ShipmentOrderRepository::class);
        $orders->shouldReceive('getByExternalOrderId')->zeroOrMoreTimes()->andReturnNull();
        $orders->shouldReceive('nextIdentity')->zeroOrMoreTimes();
        $orders->shouldReceive('save')->zeroOrMoreTimes();

        $events = Mockery::mock(DomainEventService::class);
        $events->shouldReceive('record')->zeroOrMoreTimes();

        $audit = Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('log')->zeroOrMoreTimes();

        $calculator = Mockery::mock(\App\Application\Fulfillment\Orders\Packaging\OrderPackageCalculator::class);
        $calculator->shouldReceive('calculate')->andReturn([]);

        return new PlentyOrderSyncService($gateway, $orders, $events, $audit, $calculator);
    }
}
