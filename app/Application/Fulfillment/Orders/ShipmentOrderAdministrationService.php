<?php

namespace App\Application\Fulfillment\Orders;

use App\Application\Fulfillment\Orders\Commands\TransferShipmentOrderTracking;
use App\Application\Fulfillment\Shipments\ManualShipmentService;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Fulfillment\Orders\ShipmentOrderPaginationResult;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ShipmentOrderAdministrationService
{
    public function __construct(
        private readonly ShipmentOrderRepository $orders,
        private readonly PlentyOrderSyncService $plentySync,
        private readonly TransferShipmentOrderTracking $transferTracking,
        private readonly ManualShipmentService $manualShipments,
    ) {}

    /**
     * @param  array<string,mixed>  $filters
     * @return array{requested:int,synced:int,created:int,updated:int,errors:array<int,string>,order_ids:array<int,int>}
     */
    public function syncVisible(array $filters, string $scope, int $page, int $perPage): array
    {
        $scope = $scope === 'all' ? 'all' : 'page';
        $perPage = max(1, min(200, $perPage));
        $page = max(1, $page);

        $orderIds = [];

        if ($scope === 'page') {
            $pagination = $this->orders->paginate($page, $perPage, $filters);
            $orderIds = $this->extractOrderIds($pagination);
        } else {
            $current = 1;
            do {
                $pagination = $this->orders->paginate($current, $perPage, $filters);
                $orderIds = array_merge($orderIds, $this->extractOrderIds($pagination));
                $current++;
            } while ($current <= $pagination->totalPages());
        }

        $orderIds = array_values(array_unique(array_filter($orderIds, static fn (int $id) => $id > 0)));
        if ($orderIds === []) {
            return [
                'requested' => 0,
                'synced' => 0,
                'created' => 0,
                'updated' => 0,
                'errors' => [],
                'order_ids' => [],
            ];
        }

        $summary = $this->plentySync->syncOrdersByIds($orderIds);
        $summary['order_ids'] = $orderIds;

        return $summary;
    }

    /**
     * @return array{processed:int,tracking_events:int,errors:array<int,string>}
     */
    public function syncBooked(int $limit = 500): array
    {
        $limit = max(1, $limit);

        $pagination = $this->orders->paginate(1, $limit, [
            'filter' => 'booked',
            'direction' => 'desc',
        ]);

        $processed = 0;
        $trackingEvents = 0;
        $errors = [];

        foreach ($pagination->orders as $order) {
            $trackingNumbers = $order->trackingNumbers();
            if ($trackingNumbers === []) {
                continue;
            }

            try {
                ($this->transferTracking)($order->id(), null, true);
                $processed++;
                $trackingEvents += count($trackingNumbers);
            } catch (\Throwable $exception) {
                $errors[$order->externalOrderId()] = $exception->getMessage();
            }
        }

        return [
            'processed' => $processed,
            'tracking_events' => $trackingEvents,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{
     *     summary: array{requested:int,synced:int,created:int,updated:int,errors:array<int,string>},
     *     linked_shipment_id:?int,
     *     tracking_transferred:bool
     * }
     */
    public function manualSync(int $externalOrderId, ?string $trackingNumber, bool $syncImmediately): array
    {
        $trackingNumber = $trackingNumber !== null ? trim($trackingNumber) : null;

        return DB::transaction(function () use ($externalOrderId, $trackingNumber, $syncImmediately): array {
            $summary = $this->plentySync->syncOrdersByIds([$externalOrderId]);

            $trackingTransferred = false;
            $linkedShipmentId = null;

            if ($trackingNumber === null || $trackingNumber === '') {
                return [
                    'summary' => $summary,
                    'linked_shipment_id' => null,
                    'tracking_transferred' => false,
                ];
            }

            $order = $this->orders->getByExternalOrderId($externalOrderId);
            if (! $order) {
                throw new RuntimeException(sprintf('Auftrag #%d wurde nicht gefunden.', $externalOrderId));
            }

            $shipment = $this->manualShipments->findOrCreate($trackingNumber);

            $this->orders->linkShipment($order->id(), $shipment->id());
            $linkedShipmentId = $shipment->id()->toInt();

            if ($syncImmediately) {
                try {
                    ($this->transferTracking)($order->id(), $trackingNumber, true);
                    $trackingTransferred = true;
                } catch (\Throwable) {
                    $trackingTransferred = false;
                }
            }

            return [
                'summary' => $summary,
                'linked_shipment_id' => $linkedShipmentId,
                'tracking_transferred' => $trackingTransferred,
            ];
        });
    }

    /**
     * @return array<int,int>
     */
    private function extractOrderIds(ShipmentOrderPaginationResult $pagination): array
    {
        return array_map(
            static fn ($order) => $order->externalOrderId(),
            $pagination->orders
        );
    }
}
