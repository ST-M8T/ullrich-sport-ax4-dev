<?php

declare(strict_types=1);

namespace App\View\Composers\Fulfillment;

use Illuminate\View\View;

/**
 * Fulfillment Order Show View Composer
 * Bereitet Order-Detail-Daten vor
 * SOLID: Single Responsibility - Nur Order-Show-Daten vorbereiten
 * DDD: Presentation Layer - View-spezifische Daten
 */
final class FulfillmentOrderShowComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        if (! isset($data['order'])) {
            return;
        }

        $order = $data['order'];
        $items = $order->items();
        $packages = $order->packages();
        $trackingNumbers = $order->trackingNumbers();

        $totalItemCount = array_sum(array_map(fn ($item) => $item->quantity(), $items));
        $totalPackageCount = array_sum(array_map(fn ($package) => $package->quantity(), $packages));
        $totalPackageWeight = array_reduce(
            $packages,
            fn ($carry, $package) => $carry + ($package->weightKg() ?? 0.0),
            0.0
        );

        $view->with([
            'items' => $items,
            'packages' => $packages,
            'trackingNumbers' => $trackingNumbers,
            'totalItemCount' => $totalItemCount,
            'totalPackageCount' => $totalPackageCount,
            'totalPackageWeight' => $totalPackageWeight,
        ]);
    }
}
