<?php

namespace Tests\Feature\Console;

use App\Application\Fulfillment\Shipments\DhlTrackingSyncService;
use App\Application\Fulfillment\Shipments\ShipmentTrackingService;
use App\Application\Monitoring\AuditLogger;
use App\Application\Monitoring\DomainEventService;
use App\Domain\Fulfillment\Shipments\Contracts\ShipmentRepository;
use App\Domain\Integrations\Contracts\DhlTrackingGateway;
use Mockery;
use Tests\TestCase;

final class DhlSyncTrackingCommandTest extends TestCase
{
    public function test_sync_tracking_invokes_service(): void
    {
        $gateway = new class implements DhlTrackingGateway
        {
            /** @var list<string> */
            public array $called = [];

            public function fetchTrackingEvents(string $trackingNumber): array
            {
                $this->called[] = $trackingNumber;

                return [];
            }

            public function ping(): array
            {
                return [
                    'status' => 200,
                    'duration_ms' => 0.0,
                    'body' => null,
                ];
            }
        };

        $shipmentRepository = Mockery::mock(ShipmentRepository::class)->shouldIgnoreMissing();
        $domainEvents = Mockery::mock(DomainEventService::class)->shouldIgnoreMissing();
        $auditLogger = Mockery::mock(AuditLogger::class)->shouldIgnoreMissing();

        $trackingService = new ShipmentTrackingService($shipmentRepository, $domainEvents, $auditLogger);

        $service = new DhlTrackingSyncService($gateway, $trackingService);

        $this->app->instance(DhlTrackingSyncService::class, $service);

        $this->artisan('dhl:tracking:sync', ['tracking' => 'TRACK-123'])
            ->expectsOutput('Tracking synced for TRACK-123')
            ->assertExitCode(0);

        $this->assertSame(['TRACK-123'], $gateway->called);
    }
}
