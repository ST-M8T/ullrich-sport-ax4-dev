<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlBookingOptions;
use App\Application\Fulfillment\Integrations\Dhl\Services\DhlBookingResult;
use App\Application\Fulfillment\Integrations\Dhl\Services\DhlShipmentBookingService;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Shared\ValueObjects\Identifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queue job for processing bulk DHL bookings that exceed the sync threshold.
 * Each order is processed within its own database transaction.
 *
 * The job receives the full {@see DhlBookingOptions} DTO so that productCode,
 * payerCode, additionalServices, pickupDate and defaultPackageType survive the
 * dispatch boundary unchanged. (See engineering handbook §25 — queue jobs must
 * forward the use-case input completely; data loss between dispatch and handle
 * is forbidden.)
 */
final class ProcessDhlBulkBookingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $maxExceptions = 3;

    /**
     * @param  array<int>  $orderIds
     */
    public function __construct(
        private readonly array $orderIds,
        private readonly DhlBookingOptions $options,
        ?string $connection = null,
        ?string $queue = null,
    ) {
        $this->connection = $connection ?? config('queue.default');
        $this->queue = $queue ?? 'dhl-bulk-booking';
        $this->afterCommit = true;
    }

    public function backoff(): array
    {
        return [30, 90, 180];
    }

    public function handle(
        DhlShipmentBookingService $bookingService,
        ShipmentOrderRepository $orderRepository,
    ): void {
        $succeeded = 0;
        $failed = 0;

        foreach ($this->orderIds as $orderId) {
            $result = $this->processOrder(
                Identifier::fromInt($orderId),
                $bookingService,
            );

            if ($result['success']) {
                $succeeded++;
            } else {
                $failed++;
            }
        }

        Log::info('DHL bulk booking job completed', [
            'total' => count($this->orderIds),
            'succeeded' => $succeeded,
            'failed' => $failed,
        ]);
    }

    /**
     * @return array{success:bool,shipmentId?:string,trackingNumbers?:array<int,string>,error?:string}
     */
    private function processOrder(
        Identifier $orderId,
        DhlShipmentBookingService $bookingService,
    ): array {
        try {
            /** @var DhlBookingResult $result */
            $result = DB::transaction(fn () => $bookingService->bookShipment($orderId, $this->options));

            if ($result->success) {
                Log::info('DHL bulk booking job order success', [
                    'order_id' => $orderId->toInt(),
                    'shipment_id' => $result->shipmentId,
                ]);

                return [
                    'success' => true,
                    'shipmentId' => $result->shipmentId,
                    'trackingNumbers' => $result->trackingNumbers,
                ];
            }

            Log::warning('DHL bulk booking job order failed', [
                'order_id' => $orderId->toInt(),
                'error' => $result->error,
            ]);

            return [
                'success' => false,
                'error' => $result->error ?? 'Booking failed',
            ];
        } catch (Throwable $exception) {
            Log::error('DHL bulk booking job order exception', [
                'order_id' => $orderId->toInt(),
                'exception' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('DHL bulk booking job failed', [
            'order_ids' => $this->orderIds,
            'exception' => $exception->getMessage(),
        ]);
    }

    /**
     * @return array<int,string>
     */
    public function tags(): array
    {
        return [
            'dhl-bulk-booking',
            'orders:'.count($this->orderIds),
        ];
    }
}
