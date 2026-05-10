<?php

declare(strict_types=1);

namespace App\View\Composers\Fulfillment;

use App\Domain\Fulfillment\Orders\ShipmentOrderPaginationResult;
use Illuminate\View\View;

/**
 * Fulfillment Orders View Composer
 * Bereitet Filter- und Sortier-Daten für Orders-Index vor
 * SOLID: Single Responsibility - Nur Orders-View-Daten vorbereiten
 * DDD: Presentation Layer - View-spezifische Daten
 */
final class FulfillmentOrdersComposer
{
    /**
     * Bindet Orders-Daten an View
     */
    public function compose(View $view): void
    {
        $data = $view->getData();

        $pagination = $data['pagination'] ?? null;
        if (! $pagination instanceof ShipmentOrderPaginationResult) {
            return;
        }
        $queryParams = $data['query'] ?? [];
        $perPage = $data['perPage'] ?? $pagination->perPage;

        $activeFilter = $queryParams['filter'] ?? null;
        $searchTerm = $queryParams['search'] ?? '';
        $sort = $queryParams['sort'] ?? 'processed_at';
        $direction = strtolower($queryParams['direction'] ?? 'desc');
        $senderCode = $queryParams['sender_code'] ?? '';
        $destinationCountry = $queryParams['destination_country'] ?? '';
        $processedFrom = $queryParams['processed_from'] ?? '';
        $processedTo = $queryParams['processed_to'] ?? '';
        $isBookedFilter = $queryParams['is_booked'] ?? null;

        // Empty-String als Default-Filter (statt null), damit das Tab-Component
        // einen gültigen string-Key erwartet — PHP castet null zwar implizit zu '',
        // aber PhpStan-Level 3 verlangt typsichere Keys.
        $filterTabs = [
            '' => 'Alle',
            'recent' => 'Letzte 7 Tage',
            'booked' => 'Gebucht',
            'unbooked' => 'Ungebucht',
        ];

        $sortOptions = [
            'processed_at' => 'Verarbeitet',
            'order_id' => 'Auftrag-ID',
            'kunden_id' => 'Kunden-ID',
            'email' => 'E-Mail',
            'country' => 'Land',
            'rechbetrag' => 'Rechnungsbetrag',
            'booked_at' => 'Gebucht am',
            'tracking_number' => 'Tracking-Nr.',
        ];

        $orders = $pagination->orders;
        $hasTrackingColumn = collect($orders)->contains(fn ($order) => count($order->trackingNumbers()) > 0);
        $baseRoute = route('fulfillment-orders');

        $expanded = $data['expanded'] ?? [];
        $expandedOrder = $expanded['order'] ?? null;
        $expandedItems = $expanded['items'] ?? [];
        $expandedPackages = $expanded['packages'] ?? [];
        $expandedWeight = $expanded['weight'] ?? 0.0;
        $expandedId = $expanded['expand_id'] ?? null;

        $buildQuery = function (array $overrides = []) use ($queryParams) {
            $merged = array_merge($queryParams, $overrides);

            return collect($merged)
                ->reject(static fn ($value) => $value === null || $value === '' || $value === false)
                ->all();
        };

        $hiddenFields = fn () => collect([
            'filter' => $activeFilter,
            'search' => $searchTerm,
            'sort' => $sort,
            'dir' => $direction,
            'sender_code' => $senderCode,
            'destination_country' => $destinationCountry,
            'processed_from' => $processedFrom,
            'processed_to' => $processedTo,
            'is_booked' => $isBookedFilter,
            'per_page' => $perPage,
        ])->reject(static fn ($value) => $value === null || $value === '' || $value === false);

        $view->with([
            'orders' => $orders,
            'page' => $pagination->page,
            'perPage' => $perPage,
            'totalPages' => $pagination->totalPages(),
            'totalOrders' => $pagination->total,
            'activeFilter' => $activeFilter,
            'searchTerm' => $searchTerm,
            'sort' => $sort,
            'direction' => $direction,
            'senderCode' => $senderCode,
            'destinationCountry' => $destinationCountry,
            'processedFrom' => $processedFrom,
            'processedTo' => $processedTo,
            'isBookedFilter' => $isBookedFilter,
            'filterTabs' => $filterTabs,
            'sortOptions' => $sortOptions,
            'hasTrackingColumn' => $hasTrackingColumn,
            'baseRoute' => $baseRoute,
            'expandedOrder' => $expandedOrder,
            'expandedItems' => $expandedItems,
            'expandedPackages' => $expandedPackages,
            'expandedWeight' => $expandedWeight,
            'expandedId' => $expandedId,
            'buildQuery' => $buildQuery,
            'hiddenFields' => $hiddenFields,
        ]);
    }
}
