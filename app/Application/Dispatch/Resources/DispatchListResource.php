<?php

declare(strict_types=1);

namespace App\Application\Dispatch\Resources;

use App\Domain\Dispatch\DispatchList;
use App\Domain\Dispatch\DispatchMetrics;
use App\Domain\Dispatch\DispatchScan;

final class DispatchListResource
{
    private function __construct(
        private readonly DispatchList $list,
        private readonly bool $includeScans,
    ) {}

    public static function fromDomain(DispatchList $list, bool $includeScans = true): self
    {
        return new self($list, $includeScans);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $metrics = $this->list->metrics();

        $data = [
            'id' => $this->list->id()->toInt(),
            'reference' => $this->list->reference(),
            'title' => $this->list->title(),
            'status' => $this->list->status(),
            'total_orders' => $this->list->totalOrders(),
            'total_packages' => $this->list->totalPackages(),
            'total_truck_slots' => $this->list->totalTruckSlots(),
            'created_by_user_id' => $this->list->createdByUserId()?->toInt(),
            'closed_by_user_id' => $this->list->closedByUserId()?->toInt(),
            'close_requested_at' => $this->list->closeRequestedAt()?->format(DATE_ATOM),
            'close_requested_by' => $this->list->closeRequestedBy(),
            'closed_at' => $this->list->closedAt()?->format(DATE_ATOM),
            'exported_at' => $this->list->exportedAt()?->format(DATE_ATOM),
            'export_filename' => $this->list->exportFilename(),
            'totals' => $this->formatMetrics($metrics),
            'notes' => $this->list->notes(),
            'scan_count' => $this->list->scanCount(),
            'created_at' => $this->list->createdAt()->format(DATE_ATOM),
            'updated_at' => $this->list->updatedAt()->format(DATE_ATOM),
        ];

        if ($this->includeScans) {
            $data['scans'] = array_map(
                static fn (DispatchScan $scan) => DispatchScanResource::fromDomain($scan)->toArray(),
                $this->list->scans()
            );
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMetrics(DispatchMetrics $metrics): array
    {
        return [
            'total_orders' => $metrics->totalOrders(),
            'total_packages' => $metrics->totalPackages(),
            'total_items' => $metrics->totalItems(),
            'total_truck_slots' => $metrics->totalTruckSlots(),
            'metrics' => $metrics->metrics(),
        ];
    }
}
