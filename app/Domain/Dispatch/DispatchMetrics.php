<?php

declare(strict_types=1);

namespace App\Domain\Dispatch;

use InvalidArgumentException;

final class DispatchMetrics
{
    private readonly int $totalOrders;

    private readonly int $totalPackages;

    private readonly int $totalItems;

    private readonly int $totalTruckSlots;

    /**
     * @var array<string,mixed>
     */
    private readonly array $metrics;

    /**
     * @psalm-param array<string,mixed> $metrics
     */
    private function __construct(int $totalOrders, int $totalPackages, int $totalItems, int $totalTruckSlots, array $metrics)
    {
        $this->totalOrders = self::normalizeTotal($totalOrders, 'total_orders');
        $this->totalPackages = self::normalizeTotal($totalPackages, 'total_packages');
        $this->totalItems = self::normalizeTotal($totalItems, 'total_items');
        $this->totalTruckSlots = self::normalizeTotal($totalTruckSlots, 'total_truck_slots');
        $this->metrics = $metrics;
    }

    /**
     * @psalm-param array<string,mixed> $metrics
     */
    public static function hydrate(int $totalOrders, int $totalPackages, int $totalItems, int $totalTruckSlots, array $metrics): self
    {
        return new self($totalOrders, $totalPackages, $totalItems, $totalTruckSlots, $metrics);
    }

    public function totalOrders(): int
    {
        return $this->totalOrders;
    }

    public function totalPackages(): int
    {
        return $this->totalPackages;
    }

    public function totalItems(): int
    {
        return $this->totalItems;
    }

    public function totalTruckSlots(): int
    {
        return $this->totalTruckSlots;
    }

    /**
     * @return array<string,mixed>
     */
    public function metrics(): array
    {
        return $this->metrics;
    }

    private static function normalizeTotal(int $value, string $field): int
    {
        if ($value < 0) {
            throw new InvalidArgumentException(sprintf('%s must be greater than or equal to zero.', $field));
        }

        return $value;
    }
}
