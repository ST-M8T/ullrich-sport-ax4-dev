<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fulfillment\Integrations;

use App\Application\Fulfillment\Integrations\Dhl\Services\DhlCancellationResult;
use App\Application\Fulfillment\Integrations\Dhl\Services\DhlCancellationService;
use App\Application\Fulfillment\Shipments\ShipmentTrackingService;
use App\Application\Monitoring\AuditLogger;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Fulfillment\Shipments\Contracts\ShipmentRepository;
use App\Domain\Integrations\Contracts\DhlFreightGateway;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class DhlCancellationServiceTest extends TestCase
{
    private DhlFreightGateway&MockInterface $gateway;
    private ShipmentOrderRepository&MockInterface $orderRepository;
    private ShipmentRepository&MockInterface $shipmentRepository;
    private AuditLogger&MockInterface $auditLogger;
    private LoggerInterface&MockInterface $logger;
    private ShipmentTrackingService $trackingService;
    private DhlCancellationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = Mockery::mock(DhlFreightGateway::class);
        $this->orderRepository = Mockery::mock(ShipmentOrderRepository::class);
        $this->shipmentRepository = Mockery::mock(ShipmentRepository::class);
        $this->auditLogger = Mockery::mock(AuditLogger::class);
        $this->logger = Mockery::mock(LoggerInterface::class);

        // Suppress log calls - no assertions needed on logger
        $this->logger->shouldReceive('info')->andReturnNull();
        $this->logger->shouldReceive('warning')->andReturnNull();
        $this->logger->shouldReceive('error')->andReturnNull();

        $this->trackingService = new ShipmentTrackingService($this->shipmentRepository);

        $this->service = new DhlCancellationService(
            $this->gateway,
            $this->orderRepository,
            $this->trackingService,
            $this->auditLogger,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_cancel_success_sets_cancellation_fields_on_order(): void
    {
        $orderId = 42;
        $shipmentId = 'DHL-SHIP-123';
        $reason = 'Customer requested cancellation';
        $cancelledBy = 'admin@example.com';
        $confirmationNumber = 'CNF-2026-001';
        $cancelledAt = '2026-05-11T14:30:00+00:00'; // DATE_ATOM format

        $order = $this->createShipmentOrder($orderId, $shipmentId);

        $this->orderRepository
            ->shouldReceive('getById')
            ->once()
            ->withArgs(fn ($id) => $id->toInt() === $orderId)
            ->andReturn($order);

        $this->gateway
            ->shouldReceive('cancelShipment')
            ->once()
            ->with($shipmentId, $reason)
            ->andReturn([
                'success' => true,
                'cancelled_at' => $cancelledAt,
                'confirmation_number' => $confirmationNumber,
                'error' => null,
            ]);

        $this->orderRepository
            ->shouldReceive('save')
            ->once()
            ->withArgs(function (ShipmentOrder $savedOrder) use ($cancelledAt, $cancelledBy, $reason) {
                return $savedOrder->dhlCancelledAt() !== null
                    && $savedOrder->dhlCancelledBy() === $cancelledBy
                    && $savedOrder->dhlCancellationReason() === $reason;
            });

        // Mock shipment repository for tracking service
        $this->shipmentRepository
            ->shouldReceive('getByTrackingNumber')
            ->andReturnNull(); // No shipment found, but that's ok - tracking is non-fatal

        $this->auditLogger
            ->shouldReceive('log')
            ->once()
            ->with(
                'fulfillment.dhl.shipment_cancelled',
                $cancelledBy,
                null,
                'dhl-cancellation-service',
                Mockery::type('array'),
            );

        $result = $this->service->cancel($orderId, $reason, $cancelledBy);

        $this->assertTrue($result->success);
        $this->assertSame($confirmationNumber, $result->dhlConfirmationNumber);
        $this->assertSame($cancelledAt, $result->cancelledAt);
        $this->assertNull($result->error);
    }

    public function test_cancel_order_not_found_returns_failure(): void
    {
        $orderId = 999;
        $reason = 'Test reason';
        $cancelledBy = 'test@example.com';

        $this->orderRepository
            ->shouldReceive('getById')
            ->once()
            ->withArgs(fn ($id) => $id->toInt() === $orderId)
            ->andReturn(null);

        $result = $this->service->cancel($orderId, $reason, $cancelledBy);

        $this->assertFalse($result->success);
        $this->assertNull($result->dhlConfirmationNumber);
        $this->assertNull($result->cancelledAt);
        $this->assertSame("Shipment order {$orderId} not found.", $result->error);
    }

    public function test_cancel_order_without_dhl_shipment_id_returns_failure(): void
    {
        $orderId = 42;
        $reason = 'Test reason';
        $cancelledBy = 'test@example.com';

        // Order without DHL shipment ID
        $order = $this->createShipmentOrder($orderId, null);

        $this->orderRepository
            ->shouldReceive('getById')
            ->once()
            ->withArgs(fn ($id) => $id->toInt() === $orderId)
            ->andReturn($order);

        $result = $this->service->cancel($orderId, $reason, $cancelledBy);

        $this->assertFalse($result->success);
        $this->assertNull($result->dhlConfirmationNumber);
        $this->assertNull($result->cancelledAt);
        $this->assertSame("No DHL shipment ID found for order {$orderId}.", $result->error);
    }

    public function test_cancel_already_cancelled_returns_failure_idempotency(): void
    {
        $orderId = 42;
        $shipmentId = 'DHL-SHIP-123';
        $existingCancelledAt = '2026-05-01 10:00:00';

        // Order that is already cancelled
        $order = $this->createShipmentOrder($orderId, $shipmentId, $existingCancelledAt);

        $this->orderRepository
            ->shouldReceive('getById')
            ->once()
            ->withArgs(fn ($id) => $id->toInt() === $orderId)
            ->andReturn($order);

        $result = $this->service->cancel($orderId, 'New reason', 'another@example.com');

        $this->assertFalse($result->success);
        $this->assertNull($result->dhlConfirmationNumber);
        $this->assertSame($existingCancelledAt, $result->cancelledAt);
        $this->assertSame('Shipment is already cancelled.', $result->error);
    }

    public function test_cancel_reason_is_optional_empty_string_allowed(): void
    {
        $orderId = 42;
        $shipmentId = 'DHL-SHIP-123';
        $cancelledBy = 'admin@example.com';
        $reason = '';

        $order = $this->createShipmentOrder($orderId, $shipmentId);

        $this->orderRepository
            ->shouldReceive('getById')
            ->once()
            ->andReturn($order);

        $this->gateway
            ->shouldReceive('cancelShipment')
            ->once()
            ->with($shipmentId, $reason)
            ->andReturn([
                'success' => true,
                'cancelled_at' => '2026-05-11T15:00:00+00:00', // DATE_ATOM format
                'confirmation_number' => 'CNF-EMPTY-REASON',
                'error' => null,
            ]);

        $this->orderRepository->shouldReceive('save')->once();
        $this->shipmentRepository->shouldReceive('getByTrackingNumber')->andReturnNull();
        $this->auditLogger->shouldReceive('log')->once();

        $result = $this->service->cancel($orderId, $reason, $cancelledBy);

        $this->assertTrue($result->success);
        $this->assertSame('CNF-EMPTY-REASON', $result->dhlConfirmationNumber);
    }

    public function test_cancel_cancelled_by_null_is_allowed(): void
    {
        $orderId = 42;
        $shipmentId = 'DHL-SHIP-123';
        $reason = 'System cancellation';
        $cancelledBy = '';

        $order = $this->createShipmentOrder($orderId, $shipmentId);

        $this->orderRepository
            ->shouldReceive('getById')
            ->once()
            ->andReturn($order);

        $this->gateway
            ->shouldReceive('cancelShipment')
            ->once()
            ->andReturn([
                'success' => true,
                'cancelled_at' => '2026-05-11T16:00:00+00:00', // DATE_ATOM format
                'confirmation_number' => 'CNF-NULL-BY',
                'error' => null,
            ]);

        $this->orderRepository
            ->shouldReceive('save')
            ->once()
            ->withArgs(function (ShipmentOrder $savedOrder) {
                // cancelledBy passed as empty string becomes null in domain
                // The key assertion is that cancellation succeeds despite empty cancelledBy
                return $savedOrder->dhlCancelledAt() !== null;
            });

        $this->shipmentRepository->shouldReceive('getByTrackingNumber')->andReturnNull();
        $this->auditLogger->shouldReceive('log')->once();

        $result = $this->service->cancel($orderId, $reason, $cancelledBy);

        // Cancellation should succeed even with empty cancelledBy
        $this->assertTrue($result->success);
        $this->assertSame('CNF-NULL-BY', $result->dhlConfirmationNumber);
    }

    public function test_cancel_gateway_returns_error_returns_failure(): void
    {
        $orderId = 42;
        $shipmentId = 'DHL-SHIP-123';
        $reason = 'Test reason';
        $cancelledBy = 'admin@example.com';
        $gatewayError = 'DHL API error: shipment cannot be cancelled';

        $order = $this->createShipmentOrder($orderId, $shipmentId);

        $this->orderRepository
            ->shouldReceive('getById')
            ->once()
            ->andReturn($order);

        $this->gateway
            ->shouldReceive('cancelShipment')
            ->once()
            ->andReturn([
                'success' => false,
                'cancelled_at' => null,
                'confirmation_number' => null,
                'error' => $gatewayError,
            ]);

        $result = $this->service->cancel($orderId, $reason, $cancelledBy);

        $this->assertFalse($result->success);
        $this->assertNull($result->dhlConfirmationNumber);
        $this->assertNull($result->cancelledAt);
        $this->assertSame($gatewayError, $result->error);
    }

    public function test_cancel_gateway_throws_exception_returns_failure(): void
    {
        $orderId = 42;
        $shipmentId = 'DHL-SHIP-123';
        $reason = 'Test reason';
        $cancelledBy = 'admin@example.com';

        $order = $this->createShipmentOrder($orderId, $shipmentId);

        $this->orderRepository
            ->shouldReceive('getById')
            ->once()
            ->andReturn($order);

        $this->gateway
            ->shouldReceive('cancelShipment')
            ->once()
            ->andThrow(new \RuntimeException('Connection timeout'));

        $result = $this->service->cancel($orderId, $reason, $cancelledBy);

        $this->assertFalse($result->success);
        $this->assertNull($result->dhlConfirmationNumber);
        $this->assertNull($result->cancelledAt);
        $this->assertSame('Connection timeout', $result->error);
    }

    /**
     * Helper to create a ShipmentOrder for testing.
     */
    private function createShipmentOrder(
        int $id,
        ?string $dhlShipmentId = null,
        ?string $dhlCancelledAt = null,
        array $trackingNumbers = ['003600000000000001'],
    ): ShipmentOrder {
        return ShipmentOrder::hydrate(
            Identifier::fromInt($id),
            1000 + $id,
            null,
            null,
            'standard',
            null,
            null,
            null,
            null,
            'DE',
            'EUR',
            99.99,
            null,
            false,
            null,
            null,
            null,
            null,
            [],
            [],
            $trackingNumbers,
            [],
            new DateTimeImmutable('2026-05-01'),
            new DateTimeImmutable('2026-05-01'),
            $dhlShipmentId,
            null,
            null,
            null,
            null,
            [],
            [],
            null,
            null,
            $dhlCancelledAt,
            $dhlCancelledAt !== null ? 'admin@example.com' : null,
            $dhlCancelledAt !== null ? 'Initial cancellation' : null,
        );
    }
}