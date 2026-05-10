<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dispatch\Eloquent;

use App\Domain\Dispatch\Contracts\DispatchListRepository;
use App\Domain\Dispatch\DispatchList;
use App\Domain\Dispatch\DispatchListPaginationResult;
use App\Domain\Dispatch\DispatchMetrics;
use App\Domain\Dispatch\DispatchScan;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Support\Persistence\CastsDateTime;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

final class EloquentDispatchListRepository implements DispatchListRepository
{
    use CastsDateTime;

    public function nextListIdentity(): Identifier
    {
        $next = DispatchSequenceModel::reserveNextId(
            DispatchSequenceModel::LIST_SEQUENCE,
            null,
            static fn (int $candidate): bool => DispatchListModel::query()->whereKey($candidate)->exists(),
        );

        return Identifier::fromInt($next);
    }

    public function nextScanIdentity(): Identifier
    {
        $next = DispatchSequenceModel::reserveNextId(
            DispatchSequenceModel::SCAN_SEQUENCE,
            null,
            static fn (int $candidate): bool => DispatchScanModel::query()->whereKey($candidate)->exists(),
        );

        return Identifier::fromInt($next);
    }

    public function paginate(int $page, int $perPage, array $filters = [], bool $withScans = false): DispatchListPaginationResult
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $query = DispatchListModel::query()
            ->with(['metrics'])
            ->when($withScans, fn (Builder $builder) => $builder->with('scans'))
            ->withCount('scans')
            ->when(
                isset($filters['status']),
                static fn (Builder $builder) => $builder->where('status', $filters['status']),
            )
            ->when(
                isset($filters['created_by_user_id']),
                static fn (Builder $builder) => $builder->where('created_by_user_id', $filters['created_by_user_id']),
            )
            ->when(
                isset($filters['reference']),
                static fn (Builder $builder) => $builder->where('reference', 'like', '%'.$filters['reference'].'%'),
            )
            ->orderByDesc('created_at');

        $total = (clone $query)->count();

        $records = $query->forPage($page, $perPage)->get();

        $lists = $records
            ->map(fn (DispatchListModel $model) => $this->mapList($model, includeScans: $withScans))
            ->all();

        return new DispatchListPaginationResult($lists, $page, $perPage, $total);
    }

    public function getById(Identifier $id): ?DispatchList
    {
        $model = DispatchListModel::query()
            ->with(['metrics', 'scans'])
            ->withCount('scans')
            ->find($id->toInt());

        return $model ? $this->mapList($model) : null;
    }

    public function save(DispatchList $list): void
    {
        $connection = DispatchListModel::query()->getConnection();

        $connection->transaction(function () use ($list): void {
            $this->persistList($list);
        });
    }

    public function appendScan(DispatchList $list, DispatchScan $scan): DispatchList
    {
        $connection = DispatchListModel::query()->getConnection();

        return $connection->transaction(function () use ($list, $scan): DispatchList {
            /** @var DispatchListModel|null $listModel */
            $listModel = DispatchListModel::query()
                ->lockForUpdate()
                ->with(['metrics', 'scans'])
                ->find($list->id()->toInt());

            if ($listModel === null) {
                throw new RuntimeException('Dispatch list not found.');
            }

            if ($listModel->status !== DispatchList::STATUS_OPEN) {
                throw new RuntimeException('Dispatch list is closed.');
            }

            if ($listModel->getKey() !== $scan->dispatchListId()->toInt()) {
                throw new RuntimeException('Scan does not belong to the provided dispatch list.');
            }

            $scanModel = new DispatchScanModel;
            $scanModel->id = $scan->id()->toInt();
            $scanModel->dispatch_list_id = $scan->dispatchListId()->toInt();
            $scanModel->barcode = $scan->barcode();
            $scanModel->shipment_order_id = $scan->shipmentOrderId()?->toInt();
            $scanModel->captured_by_user_id = $scan->capturedByUserId()?->toInt();
            $scanModel->captured_at = $scan->capturedAt();
            $scanModel->metadata = $scan->metadata();
            $scanModel->created_at = $scan->createdAt();
            $scanModel->updated_at = $scan->updatedAt();
            $scanModel->save();

            DispatchSequenceModel::syncToAtLeast(
                DispatchSequenceModel::SCAN_SEQUENCE,
                $scan->id()->toInt() + 1
            );

            $this->persistList($list, $listModel);

            $listModel->load(['metrics', 'scans']);

            return $this->mapList($listModel);
        });
    }

    private function persistList(DispatchList $list, ?DispatchListModel $model = null): DispatchListModel
    {
        $model ??= DispatchListModel::query()->lockForUpdate()->find($list->id()->toInt())
            ?? new DispatchListModel(['id' => $list->id()->toInt()]);

        $model->reference = $list->reference();
        $model->title = $list->title();
        $model->status = $list->status();
        $model->created_by_user_id = $list->createdByUserId()?->toInt();
        $model->closed_by_user_id = $list->closedByUserId()?->toInt();
        $model->close_requested_at = $list->closeRequestedAt();
        $model->close_requested_by = $list->closeRequestedBy();
        $model->closed_at = $list->closedAt();
        $model->exported_at = $list->exportedAt();
        $model->export_filename = $list->exportFilename();
        $model->total_packages = $list->totalPackages();
        $model->total_orders = $list->totalOrders();
        $model->total_truck_slots = $list->totalTruckSlots();
        $model->notes = $list->notes();
        $model->created_at = $list->createdAt();
        $model->updated_at = $list->updatedAt();
        $model->save();

        $this->persistMetrics($list->id(), $list->metrics());

        DispatchSequenceModel::syncToAtLeast(
            DispatchSequenceModel::LIST_SEQUENCE,
            $list->id()->toInt() + 1
        );

        return $model;
    }

    private function persistMetrics(Identifier $dispatchListId, DispatchMetrics $metrics): void
    {
        DispatchMetricsModel::updateOrCreate(
            ['dispatch_list_id' => $dispatchListId->toInt()],
            [
                'total_orders' => $metrics->totalOrders(),
                'total_packages' => $metrics->totalPackages(),
                'total_items' => $metrics->totalItems(),
                'total_truck_slots' => $metrics->totalTruckSlots(),
                'metrics' => $metrics->metrics(),
            ],
        );
    }

    private function mapList(DispatchListModel $model, bool $includeScans = true): DispatchList
    {
        $metrics_model = $model->metrics;
        $raw_metrics = $metrics_model?->metrics;

        // Wenn der HasOne-Metrics-Eintrag fehlt: Fallback auf die direkten Spalten
        // im DispatchListModel (Legacy-Daten ohne separates Metrics-Aggregat).
        $metrics = DispatchMetrics::hydrate(
            $metrics_model !== null ? $metrics_model->total_orders : ($model->total_orders ?? 0),
            $metrics_model !== null ? $metrics_model->total_packages : ($model->total_packages ?? 0),
            $metrics_model !== null ? $metrics_model->total_items : 0,
            $metrics_model !== null ? $metrics_model->total_truck_slots : ($model->total_truck_slots ?? 0),
            is_array($raw_metrics) ? $raw_metrics : [],
        );

        $scans = [];
        if ($includeScans) {
            $scans = $model->scans
                ->map(fn (DispatchScanModel $scan) => $this->mapScan($scan))
                ->all();
        }

        $total_packages = $model->total_packages ?? $metrics->totalPackages();
        $total_orders = $model->total_orders ?? $metrics->totalOrders();
        $total_truck_slots = $model->total_truck_slots ?? $metrics->totalTruckSlots();
        $scan_count = $includeScans
            ? count($scans)
            : (int) ($model->scans_count ?? 0);

        $status = $model->status;
        $created_by_user_id = $model->created_by_user_id !== null
            ? Identifier::fromInt((int) $model->created_by_user_id)
            : null;
        $closed_by_user_id = $model->closed_by_user_id !== null
            ? Identifier::fromInt((int) $model->closed_by_user_id)
            : null;
        $close_requested_at = $this->toImmutable($model->close_requested_at);
        $closed_at = $this->toImmutable($model->closed_at);
        $export_filename = $model->export_filename;
        $exported_at = $this->toImmutable($model->exported_at);

        $created_at = $this->toImmutable($model->created_at) ?? new DateTimeImmutable;
        $updated_at = $this->toImmutable($model->updated_at) ?? new DateTimeImmutable;

        return DispatchList::hydrate(
            Identifier::fromInt((int) $model->getKey()),
            $model->reference,
            $model->title,
            $status,
            $created_by_user_id,
            $closed_by_user_id,
            $close_requested_at,
            $model->close_requested_by,
            $closed_at,
            $exported_at,
            $export_filename,
            $total_packages,
            $total_orders,
            $total_truck_slots,
            $model->notes,
            $metrics,
            $scans,
            $created_at,
            $updated_at,
            $scan_count,
        );
    }

    private function mapScan(DispatchScanModel $model): DispatchScan
    {
        return DispatchScan::hydrate(
            Identifier::fromInt((int) $model->getKey()),
            Identifier::fromInt((int) $model->dispatch_list_id),
            $model->barcode,
            $model->shipment_order_id !== null ? Identifier::fromInt((int) $model->shipment_order_id) : null,
            $model->captured_by_user_id !== null ? Identifier::fromInt((int) $model->captured_by_user_id) : null,
            $this->toImmutable($model->captured_at),
            is_array($model->metadata) ? $model->metadata : [],
            $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
            $this->toImmutable($model->updated_at) ?? new DateTimeImmutable,
        );
    }
}
