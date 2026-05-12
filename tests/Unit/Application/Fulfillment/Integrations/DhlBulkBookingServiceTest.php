<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fulfillment\Integrations;

use App\Application\Fulfillment\Integrations\Dhl\Services\DhlBookingResult;
use App\Application\Fulfillment\Integrations\Dhl\Services\DhlBulkBookingService;
use App\Application\Fulfillment\Integrations\Dhl\Services\DhlShipmentBookingService;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Jobs\ProcessDhlBulkBookingJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class DhlBulkBookingServiceTest extends TestCase
{
    private DhlShipmentBookingService&MockInterface $bookingService;

    private ShipmentOrderRepository&MockInterface $orderRepository;

    private LoggerInterface&MockInterface $logger;

    private DhlBulkBookingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bookingService = Mockery::mock(DhlShipmentBookingService::class);
        $this->orderRepository = Mockery::mock(ShipmentOrderRepository::class);
        $this->logger = Mockery::mock(LoggerInterface::class);

        // Suppress log calls - no assertions needed on logger
        $this->logger->shouldReceive('info')->andReturnNull();
        $this->logger->shouldReceive('warning')->andReturnNull();
        $this->logger->shouldReceive('error')->andReturnNull();

        $this->service = new DhlBulkBookingService(
            $this->bookingService,
            $this->orderRepository,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_book_bulk_with_single_order_returns_success_with_shipment_and_tracking(): void
    {
        $orderId = 1;
        $productId = 'DHL_A0';
        $services = ['A1'];
        $pickupDate = '2026-05-15';
        $shipmentId = 'shipment-abc-123';
        $trackingNumbers = ['003600000000000001', '003600000000000002'];

        $this->bookingService
            ->shouldReceive('bookShipment')
            ->once()
            ->withArgs(fn ($id, $opts) => $id->toInt() === $orderId
                && $opts->productId() === $productId
                && $opts->pickupDate() === $pickupDate)
            ->andReturn(new DhlBookingResult(true, $shipmentId, $trackingNumbers, null));

        $result = $this->service->bookBulk([$orderId], $productId, $services, $pickupDate);

        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['succeeded']);
        $this->assertSame(0, $result['failed']);
        $this->assertFalse($result['queued']);

        $this->assertCount(1, $result['results']);
        $this->assertSame($orderId, $result['results'][0]['orderId']);
        $this->assertTrue($result['results'][0]['success']);
        $this->assertSame($shipmentId, $result['results'][0]['shipmentId']);
        $this->assertSame($trackingNumbers, $result['results'][0]['trackingNumbers']);
    }

    public function test_book_bulk_with_five_orders_returns_all_success(): void
    {
        $orderIds = [1, 2, 3, 4, 5];
        $productId = 'DHL_A0';
        $services = ['A1'];
        $pickupDate = '2026-05-15';

        foreach ($orderIds as $index => $orderId) {
            $this->bookingService
                ->shouldReceive('bookShipment')
                ->once()
                ->withArgs(fn ($id) => $id->toInt() === $orderId)
                ->andReturn(new DhlBookingResult(
                    true,
                    "shipment-{$orderId}",
                    ["TRACK{$orderId}-01", "TRACK{$orderId}-02"],
                    null,
                ));
        }

        $result = $this->service->bookBulk($orderIds, $productId, $services, $pickupDate);

        $this->assertSame(5, $result['total']);
        $this->assertSame(5, $result['succeeded']);
        $this->assertSame(0, $result['failed']);
        $this->assertFalse($result['queued']);

        $this->assertCount(5, $result['results']);
        foreach ($result['results'] as $index => $orderResult) {
            $this->assertSame($orderIds[$index], $orderResult['orderId']);
            $this->assertTrue($orderResult['success']);
            $this->assertNotEmpty($orderResult['shipmentId']);
            $this->assertNotEmpty($orderResult['trackingNumbers']);
        }
    }

    public function test_book_bulk_with_ten_orders_processes_synchronously_without_queue_job(): void
    {
        $orderIds = range(1, 10);
        $productId = 'DHL_A0';
        $services = ['A1'];
        $pickupDate = '2026-05-15';

        Queue::fake();

        foreach ($orderIds as $orderId) {
            $this->bookingService
                ->shouldReceive('bookShipment')
                ->once()
                ->withArgs(fn ($id) => $id->toInt() === $orderId)
                ->andReturn(new DhlBookingResult(true, "shipment-{$orderId}", ["TRACK{$orderId}"], null));
        }

        $result = $this->service->bookBulk($orderIds, $productId, $services, $pickupDate);

        $this->assertSame(10, $result['total']);
        $this->assertSame(10, $result['succeeded']);
        $this->assertSame(0, $result['failed']);
        $this->assertFalse($result['queued']);

        Queue::assertNothingPushed();
    }

    public function test_book_bulk_with_gateway_exception_on_one_order_partial_success(): void
    {
        $orderIds = [1, 2, 3];
        $productId = 'DHL_A0';
        $services = ['A1'];
        $pickupDate = '2026-05-15';

        // Order 1: success
        $this->bookingService
            ->shouldReceive('bookShipment')
            ->once()
            ->withArgs(fn ($id) => $id->toInt() === 1)
            ->andReturn(new DhlBookingResult(true, 'shipment-1', ['TRACK1'], null));

        // Order 2: booking service throws exception (simulates gateway error)
        $this->bookingService
            ->shouldReceive('bookShipment')
            ->once()
            ->withArgs(fn ($id) => $id->toInt() === 2)
            ->andThrow(new RuntimeException('DHL gateway timeout'));

        // Order 3: success
        $this->bookingService
            ->shouldReceive('bookShipment')
            ->once()
            ->withArgs(fn ($id) => $id->toInt() === 3)
            ->andReturn(new DhlBookingResult(true, 'shipment-3', ['TRACK3'], null));

        $result = $this->service->bookBulk($orderIds, $productId, $services, $pickupDate);

        $this->assertSame(3, $result['total']);
        $this->assertSame(2, $result['succeeded']);
        $this->assertSame(1, $result['failed']);
        $this->assertFalse($result['queued']);

        $this->assertTrue($result['results'][0]['success']);
        $this->assertFalse($result['results'][1]['success']);
        $this->assertSame('DHL gateway timeout', $result['results'][1]['error']);
        $this->assertTrue($result['results'][2]['success']);
    }

    public function test_book_bulk_with_all_exceptions_all_orders_fail(): void
    {
        $orderIds = [1, 2, 3];
        $productId = 'DHL_A0';
        $services = ['A1'];
        $pickupDate = '2026-05-15';

        foreach ($orderIds as $orderId) {
            $this->bookingService
                ->shouldReceive('bookShipment')
                ->once()
                ->withArgs(fn ($id) => $id->toInt() === $orderId)
                ->andThrow(new RuntimeException("Gateway error for order {$orderId}"));
        }

        $result = $this->service->bookBulk($orderIds, $productId, $services, $pickupDate);

        $this->assertSame(3, $result['total']);
        $this->assertSame(0, $result['succeeded']);
        $this->assertSame(3, $result['failed']);
        $this->assertFalse($result['queued']);

        foreach ($result['results'] as $index => $orderResult) {
            $this->assertFalse($orderResult['success']);
            $this->assertNotEmpty($orderResult['error']);
        }
    }

    public function test_book_bulk_exceeding_threshold_dispatches_queue_job(): void
    {
        $orderIds = range(1, 11);
        $productId = 'DHL_A0';
        $services = ['A1'];
        $pickupDate = '2026-05-15';

        Queue::fake();

        // No booking service calls expected synchronously when bulk is queued
        $result = $this->service->bookBulk($orderIds, $productId, $services, $pickupDate);

        $this->assertSame(11, $result['total']);
        $this->assertSame(0, $result['succeeded']);
        $this->assertSame(0, $result['failed']);
        $this->assertTrue($result['queued']);

        // Outcome assertion: a job is pushed whose public surface (tags) reflects the order count.
        // The job's effect is verified separately via handle() in
        // test_queued_job_when_handled_books_each_order_via_booking_service.
        Queue::assertPushed(ProcessDhlBulkBookingJob::class, function (ProcessDhlBulkBookingJob $job): bool {
            $this->assertContains('dhl-bulk-booking', $job->tags());
            $this->assertContains('orders:11', $job->tags());

            return true;
        });
    }

    public function test_book_bulk_with_exactly_eleven_orders_dispatches_one_job_with_eleven_orders(): void
    {
        $orderIds = range(100, 110); // 11 orders starting at 100
        $productId = 'DHL_B2';
        $services = ['A1', 'B2'];
        $pickupDate = '2026-06-01';

        Queue::fake();

        $result = $this->service->bookBulk($orderIds, $productId, $services, $pickupDate);

        $this->assertSame(11, $result['total']);
        $this->assertTrue($result['queued']);

        // One job dispatched, tagged with the expected order count.
        Queue::assertPushed(ProcessDhlBulkBookingJob::class, 1);
        Queue::assertPushed(ProcessDhlBulkBookingJob::class, function (ProcessDhlBulkBookingJob $job): bool {
            return in_array('orders:11', $job->tags(), true);
        });
    }

    /**
     * Outcome verification for the queued path: instead of reading private constructor
     * arguments off the dispatched job (whitebox), capture the job, invoke its handle()
     * with a mocked booking service, and assert that bookShipment is called once per
     * orderId originally passed to the bulk service.
     */
    public function test_queued_job_when_handled_books_each_order_via_booking_service(): void
    {
        $orderIds = range(1, 11);
        $productId = 'V01'; // valid 3-char DhlProductCode → forwarded as productCode VO
        $services = ['A1', 'B2'];
        $pickupDate = '2026-05-15';
        $payerCode = 'DAP';
        $packageType = 'PLT';

        Queue::fake();

        $this->service->bookBulk($orderIds, $productId, $services, $pickupDate, $payerCode, $packageType);

        $pushed = Queue::pushed(ProcessDhlBulkBookingJob::class);
        $this->assertCount(1, $pushed);

        /** @var ProcessDhlBulkBookingJob $job */
        $job = $pushed->first();

        $bookingService = Mockery::mock(DhlShipmentBookingService::class);
        $orderRepository = Mockery::mock(ShipmentOrderRepository::class);

        $seenOrderIds = [];
        $seenOptions = [];
        $bookingService
            ->shouldReceive('bookShipment')
            ->andReturnUsing(function ($id, $options) use (&$seenOrderIds, &$seenOptions) {
                $seenOrderIds[] = $id->toInt();
                $seenOptions[] = $options;

                return new DhlBookingResult(true, 'shipment-'.$id->toInt(), ['T'.$id->toInt()], null);
            });

        DB::shouldReceive('transaction')->andReturnUsing(fn (callable $cb) => $cb());

        $job->handle($bookingService, $orderRepository);

        // Outcome: every orderId originally passed to the bulk service is forwarded
        // to the booking service exactly once when the queued job is handled.
        $this->assertSame($orderIds, $seenOrderIds);

        // And the booking options survive the dispatch boundary unchanged.
        $this->assertNotEmpty($seenOptions);
        $first = $seenOptions[0];
        $this->assertSame($productId, $first->productId());
        $this->assertNotNull($first->productCode());
        $this->assertSame($productId, (string) $first->productCode());
        $this->assertNotNull($first->payerCode());
        $this->assertSame($payerCode, $first->payerCode()->value);
        $this->assertNotNull($first->defaultPackageType());
        $this->assertSame($packageType, (string) $first->defaultPackageType());
        $this->assertSame($pickupDate, $first->pickupDate());
        $this->assertCount(count($services), $first->serviceOptions()->all());
    }

    public function test_book_bulk_empty_order_array_returns_empty_result(): void
    {
        $result = $this->service->bookBulk([], 'DHL_A0', ['A1'], '2026-05-15');

        $this->assertSame(0, $result['total']);
        $this->assertSame(0, $result['succeeded']);
        $this->assertSame(0, $result['failed']);
        $this->assertFalse($result['queued']);
        $this->assertEmpty($result['results']);
    }
}
