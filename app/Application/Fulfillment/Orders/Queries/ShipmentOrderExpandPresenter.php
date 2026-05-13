<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Orders\Queries;

use App\Domain\Fulfillment\Orders\ShipmentOrder;

/**
 * Builds the view-model array for an "expanded" row inside the shipment-order
 * index table.
 *
 * Extracted from {@see \App\Http\Controllers\Fulfillment\ShipmentOrderController}
 * so that Presentation contains no shaping logic (Handbook §7).
 */
final class ShipmentOrderExpandPresenter
{
    /**
     * @param  iterable<ShipmentOrder>  $orders
     * @return array{order: ?ShipmentOrder, items: array<int,array<string,mixed>>, packages: array<int,array<string,mixed>>, weight: float, expand_id: ?int}
     */
    public function present(iterable $orders, ?int $expandId): array
    {
        $empty = [
            'order' => null,
            'items' => [],
            'packages' => [],
            'weight' => 0.0,
            'expand_id' => $expandId,
        ];

        if ($expandId === null) {
            return $empty;
        }

        foreach ($orders as $order) {
            if ($order->externalOrderId() !== $expandId) {
                continue;
            }

            return [
                'order' => $order,
                'items' => $this->mapItems($order),
                'packages' => $this->mapPackages($order),
                'weight' => $this->totalWeight($order),
                'expand_id' => $expandId,
            ];
        }

        return $empty;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function mapItems(ShipmentOrder $order): array
    {
        $items = [];
        foreach ($order->items() as $item) {
            $items[] = [
                'sku' => $item->sku(),
                'description' => $item->description(),
                'quantity' => $item->quantity(),
                'weight' => $item->weightKg(),
                'is_assembly' => $item->isAssembly(),
                'packaging_profile_id' => $item->packagingProfileId()?->toInt(),
            ];
        }

        return $items;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function mapPackages(ShipmentOrder $order): array
    {
        $packages = [];
        foreach ($order->packages() as $package) {
            $packages[] = [
                'reference' => $package->packageReference(),
                'quantity' => $package->quantity(),
                'weight' => $package->weightKg(),
                'dimensions' => [
                    $package->lengthMillimetres(),
                    $package->widthMillimetres(),
                    $package->heightMillimetres(),
                ],
                'truck_slots' => $package->truckSlotUnits(),
            ];
        }

        return $packages;
    }

    private function totalWeight(ShipmentOrder $order): float
    {
        $total = 0.0;
        foreach ($order->items() as $item) {
            $total += ($item->weightKg() ?? 0.0) * max(1, $item->quantity());
        }

        return $total;
    }
}
