<?php

declare(strict_types=1);

namespace App\Application\Dispatch\Queries;

use App\Domain\Dispatch\DispatchScan;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Fulfillment\Orders\ShipmentOrderItem;
use App\Domain\Fulfillment\Orders\ShipmentPackage;
use App\Domain\Identity\Contracts\UserRepository;

final class EnrichDispatchScans
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ShipmentOrderRepository $orders,
    ) {
        // Constructor is used for dependency injection only.
    }

    /**
     * @param  array<int,DispatchScan>  $scans
     * @return array<int,array{scan: DispatchScan, username: ?string, order_details: ?array<string, mixed>}>
     */
    public function __invoke(array $scans): array
    {
        return array_map(
            fn (DispatchScan $scan) => $this->enrichScan($scan),
            $scans
        );
    }

    /**
     * @return array{scan: DispatchScan, username: ?string, order_details: ?array<string, mixed>}
     */
    private function enrichScan(DispatchScan $scan): array
    {
        $username = $this->resolveUsername($scan);
        $orderDetails = $this->resolveOrderDetails($scan);

        return [
            'scan' => $scan,
            'username' => $username,
            'order_details' => $orderDetails,
        ];
    }

    private function resolveUsername(DispatchScan $scan): ?string
    {
        $userId = $scan->capturedByUserId();
        if ($userId === null) {
            return null;
        }

        $user = $this->users->getById($userId);
        if ($user === null) {
            return null;
        }

        return $user->displayName() ?? $user->username();
    }

    /**
     * @return array<string,mixed>|null
     */
    private function resolveOrderDetails(DispatchScan $scan): ?array
    {
        $orderId = $scan->shipmentOrderId();
        if ($orderId === null) {
            return null;
        }

        $order = $this->orders->getById($orderId);
        if ($order === null) {
            return null;
        }

        return $this->buildOrderDetails($order);
    }

    /**
     * @return array<string,mixed>
     */
    private function buildOrderDetails(ShipmentOrder $order): array
    {
        $items = $this->buildItems($order->items());
        $packages = $this->buildPackages($order->packages());
        $totals = $this->calculateTotals($packages);

        return [
            'external_order_id' => $order->externalOrderId(),
            'order_type' => $order->orderType(),
            'destination_country' => $order->destinationCountry(),
            'tracking_numbers' => $order->trackingNumbers(),
            'items' => $items,
            'packages' => $packages,
            'total_weight_kg' => $totals['weight'],
            'total_truck_slots' => $totals['slots'],
        ];
    }

    /**
     * @param  array<int,ShipmentOrderItem>  $items
     * @return array<int,array{sku: ?string, description: ?string, quantity: int, weight_kg: ?float}>
     */
    private function buildItems(array $items): array
    {
        return array_map(
            fn ($item) => [
                'sku' => $item->sku(),
                'description' => $item->description(),
                'quantity' => $item->quantity(),
                'weight_kg' => $item->weightKg(),
            ],
            $items
        );
    }

    /**
     * @param  array<int,ShipmentPackage>  $packages
     * @return array<int,array{reference: ?string, quantity: int, dimensions: array{length_mm: ?int, width_mm: ?int, height_mm: ?int}, weight_kg: ?float, truck_slots: int}>
     */
    private function buildPackages(array $packages): array
    {
        return array_map(
            fn ($package) => [
                'reference' => $package->packageReference(),
                'quantity' => $package->quantity(),
                'dimensions' => [
                    'length_mm' => $package->lengthMillimetres(),
                    'width_mm' => $package->widthMillimetres(),
                    'height_mm' => $package->heightMillimetres(),
                ],
                'weight_kg' => $package->weightKg(),
                'truck_slots' => $package->truckSlotUnits(),
            ],
            $packages
        );
    }

    /**
     * @param  array<int,array{quantity: int, weight_kg: ?float, truck_slots: int}>  $packages
     * @return array{weight: ?float, slots: int}
     */
    private function calculateTotals(array $packages): array
    {
        $totalWeight = 0.0;
        $totalSlots = 0;

        foreach ($packages as $pkg) {
            $quantity = $pkg['quantity'] ?? 1;
            if ($pkg['weight_kg'] !== null) {
                $totalWeight += $pkg['weight_kg'] * $quantity;
            }
            $totalSlots += ($pkg['truck_slots'] ?? 0) * $quantity;
        }

        return [
            'weight' => $totalWeight > 0 ? round($totalWeight, 2) : null,
            'slots' => $totalSlots,
        ];
    }
}
