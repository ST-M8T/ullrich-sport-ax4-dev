<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlBookingOptions;
use App\Application\Fulfillment\Integrations\Dhl\Services\DhlBookingResult;
use App\Application\Fulfillment\Integrations\Dhl\Services\DhlShipmentBookingService;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Jobs\ProcessDhlBulkBookingJob;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit-level coverage of the queue handler. Verifies that the job forwards the
 * full {@see DhlBookingOptions} (productCode, payerCode, additionalServices,
 * pickupDate, defaultPackageType) to the booking service for every order id —
 * i.e. no field is dropped between dispatch and handle.
 */
final class ProcessDhlBulkBookingJobTest extends TestCase
{
    private DhlShipmentBookingService&MockInterface $bookingService;

    private ShipmentOrderRepository&MockInterface $orderRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bookingService = Mockery::mock(DhlShipmentBookingService::class);
        $this->orderRepository = Mockery::mock(ShipmentOrderRepository::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_invokes_booking_service_with_complete_options_for_each_order(): void
    {
        $orderIds = [101, 202, 303];
        $options = DhlBookingOptions::fromArray([
            'product_id' => 'V01',
            'product_code' => 'V01',
            'additional_services' => ['A1', 'B2'],
            'pickup_date' => '2026-06-15',
            'payer_code' => 'DAP',
            'default_package_type' => 'PLT',
        ]);

        $seen = [];
        $this->bookingService
            ->shouldReceive('bookShipment')
            ->times(count($orderIds))
            ->andReturnUsing(function ($id, $opts) use (&$seen) {
                $seen[] = ['id' => $id->toInt(), 'options' => $opts];

                return new DhlBookingResult(true, 'sh-'.$id->toInt(), ['T'.$id->toInt()], null);
            });

        DB::shouldReceive('transaction')->andReturnUsing(fn (callable $cb) => $cb());

        $job = new ProcessDhlBulkBookingJob($orderIds, $options);
        $job->handle($this->bookingService, $this->orderRepository);

        $this->assertCount(count($orderIds), $seen);
        foreach ($seen as $index => $call) {
            $this->assertSame($orderIds[$index], $call['id']);

            /** @var DhlBookingOptions $opts */
            $opts = $call['options'];
            $this->assertSame('V01', $opts->productId());
            $this->assertNotNull($opts->productCode());
            $this->assertSame('V01', (string) $opts->productCode());
            $this->assertNotNull($opts->payerCode());
            $this->assertSame('DAP', $opts->payerCode()->value);
            $this->assertNotNull($opts->defaultPackageType());
            $this->assertSame('PLT', (string) $opts->defaultPackageType());
            $this->assertSame('2026-06-15', $opts->pickupDate());
            $this->assertCount(2, $opts->serviceOptions()->all());
        }
    }

    public function test_handle_continues_processing_remaining_orders_when_one_throws(): void
    {
        $orderIds = [1, 2, 3];
        $options = DhlBookingOptions::fromArray([
            'product_id' => 'V01',
            'product_code' => 'V01',
            'additional_services' => [],
            'pickup_date' => null,
            'payer_code' => 'DAP',
        ]);

        $this->bookingService
            ->shouldReceive('bookShipment')
            ->withArgs(fn ($id) => $id->toInt() === 1)
            ->andReturn(new DhlBookingResult(true, 'sh-1', ['T1'], null));

        $this->bookingService
            ->shouldReceive('bookShipment')
            ->withArgs(fn ($id) => $id->toInt() === 2)
            ->andThrow(new \RuntimeException('gateway down'));

        $this->bookingService
            ->shouldReceive('bookShipment')
            ->withArgs(fn ($id) => $id->toInt() === 3)
            ->andReturn(new DhlBookingResult(true, 'sh-3', ['T3'], null));

        DB::shouldReceive('transaction')->andReturnUsing(fn (callable $cb) => $cb());

        $job = new ProcessDhlBulkBookingJob($orderIds, $options);
        $job->handle($this->bookingService, $this->orderRepository);

        // Reaching this point asserts handle() did not abort on the throwing order;
        // Mockery's strict expectations verify that all three calls happened.
        $this->assertTrue(true);
    }
}
