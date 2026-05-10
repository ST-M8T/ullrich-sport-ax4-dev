<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Exports;

use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Fulfillment\Orders\ShipmentOrderItem;
use App\Domain\Fulfillment\Orders\ShipmentPackage;
use DateTimeImmutable;

final class ShipmentExportGenerator
{
    private const DEFAULT_HEADERS = [
        'Bestellnummer',
        'Verarbeitet am',
        'Gebucht',
        'Gebucht am',
        'Sender',
        'Zielland',
        'Währung',
        'Gesamtbetrag',
        'Artikel',
        'Pakete / Maße',
        'Tracking',
        'Letzte Exportdatei',
    ];

    public function __construct(
        private readonly ShipmentOrderRepository $orders,
        private readonly int $pageSize = 250,
    ) {}

    public function generate(ShipmentExportFilters $filters, ?int $externalOrderId = null): ShipmentExportResult
    {
        $rows = [];
        $orderCount = 0;

        if ($externalOrderId !== null) {
            $order = $this->orders->getByExternalOrderId($externalOrderId);
            if ($order && $this->matchesFilters($order, $filters)) {
                $rows[] = $this->mapOrder($order);
                $orderCount = 1;
            }

            return new ShipmentExportResult(self::DEFAULT_HEADERS, $rows, $orderCount);
        }

        $repositoryFilters = $filters->toRepositoryFilters();
        $page = 1;

        do {
            $result = $this->orders->paginate($page, $this->pageSize, $repositoryFilters);
            $orders = $result->orders;

            if (empty($orders)) {
                break;
            }

            foreach ($orders as $order) {
                $rows[] = $this->mapOrder($order);
            }

            $orderCount += count($orders);

            if (! $result->hasMorePages()) {
                break;
            }

            $page++;
        } while (true);

        return new ShipmentExportResult(self::DEFAULT_HEADERS, $rows, $orderCount);
    }

    private function matchesFilters(ShipmentOrder $order, ShipmentExportFilters $filters): bool
    {
        if ($filters->processedFrom && ! $this->isAfterOrEqual($order->processedAt(), $filters->processedFrom)) {
            return false;
        }

        if ($filters->processedTo && ! $this->isBeforeOrEqual($order->processedAt(), $filters->processedTo)) {
            return false;
        }

        if ($filters->senderCode !== null && $filters->senderCode !== '' && $order->senderCode() !== $filters->senderCode) {
            return false;
        }

        if ($filters->destinationCountry !== null && $filters->destinationCountry !== '' && $order->destinationCountry() !== $filters->destinationCountry) {
            return false;
        }

        if ($filters->isBooked !== null && $order->isBooked() !== $filters->isBooked) {
            return false;
        }

        return true;
    }

    private function isAfterOrEqual(?DateTimeImmutable $value, DateTimeImmutable $threshold): bool
    {
        if ($value === null) {
            return false;
        }

        return $value->getTimestamp() >= $threshold->getTimestamp();
    }

    private function isBeforeOrEqual(?DateTimeImmutable $value, DateTimeImmutable $threshold): bool
    {
        if ($value === null) {
            return false;
        }

        return $value->getTimestamp() <= $threshold->getTimestamp();
    }

    /**
     * @return array<int,string>
     */
    private function mapOrder(ShipmentOrder $order): array
    {
        return [
            (string) $order->externalOrderId(),
            $order->processedAt()?->format('d.m.Y H:i') ?? '',
            $order->isBooked() ? 'Ja' : 'Nein',
            $order->bookedAt()?->format('d.m.Y H:i') ?? '',
            $order->senderCode() ?? '—',
            $order->destinationCountry() ?? '—',
            $order->currency(),
            $order->totalAmount() !== null ? number_format($order->totalAmount(), 2, ',', '.') : '',
            $this->formatItems($order->items()),
            $this->formatPackages($order->packages()),
            $this->formatTrackingNumbers($order->trackingNumbers()),
            $order->lastExportFilename() ?? '—',
        ];
    }

    /**
     * @param  array<int,ShipmentOrderItem>  $items
     */
    private function formatItems(array $items): string
    {
        if (empty($items)) {
            return '—';
        }

        $parts = [];

        foreach ($items as $item) {
            $label = $item->sku() ?? $item->description() ?? 'Position';
            $quantity = $item->quantity();
            $parts[] = sprintf('%s × %d', $label, $quantity);
        }

        return implode(' | ', $parts);
    }

    /**
     * @param  array<int,ShipmentPackage>  $packages
     */
    private function formatPackages(array $packages): string
    {
        if (empty($packages)) {
            return '—';
        }

        $parts = [];

        foreach ($packages as $package) {
            $segments = [];
            $reference = $package->packageReference();

            if ($reference) {
                $segments[] = $reference;
            }

            $segments[] = '× '.$package->quantity();

            $dimensions = $this->formatDimensions(
                $package->lengthMillimetres(),
                $package->widthMillimetres(),
                $package->heightMillimetres(),
            );

            if ($dimensions !== null) {
                $segments[] = $dimensions;
            }

            if ($package->weightKg() !== null) {
                $segments[] = sprintf('%0.2f kg', $package->weightKg());
            }

            $parts[] = implode(' ', $segments);
        }

        return implode(' | ', $parts);
    }

    private function formatDimensions(?int $lengthMm, ?int $widthMm, ?int $heightMm): ?string
    {
        if ($lengthMm === null || $widthMm === null || $heightMm === null) {
            return null;
        }

        $format = fn (int $value): string => number_format($value / 10, 1, ',', '');

        return sprintf('%s×%s×%s cm', $format($lengthMm), $format($widthMm), $format($heightMm));
    }

    /**
     * @param  array<int,string>  $trackingNumbers
     */
    private function formatTrackingNumbers(array $trackingNumbers): string
    {
        if (empty($trackingNumbers)) {
            return '—';
        }

        return implode(' | ', $trackingNumbers);
    }
}
