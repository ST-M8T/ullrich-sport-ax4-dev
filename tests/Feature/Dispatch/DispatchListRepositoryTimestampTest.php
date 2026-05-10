<?php

declare(strict_types=1);

namespace Tests\Feature\Dispatch;

use App\Domain\Dispatch\Contracts\DispatchListRepository;
use App\Domain\Dispatch\DispatchList;
use App\Domain\Dispatch\DispatchMetrics;
use App\Infrastructure\Persistence\Dispatch\Eloquent\DispatchListModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DispatchListRepositoryTimestampTest extends TestCase
{
    use RefreshDatabase;

    private DispatchListRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(DispatchListRepository::class);
    }

    public function test_save_persists_domain_timestamps(): void
    {
        $identifier = $this->repository->nextListIdentity();
        $created_at = new DateTimeImmutable('2024-01-02T10:00:00+00:00');
        $updated_at = new DateTimeImmutable('2024-01-02T12:30:00+00:00');
        $metrics = DispatchMetrics::hydrate(1, 1, 0, 1, []);

        $list = DispatchList::hydrate(
            $identifier,
            'REF-'.$identifier->toInt(),
            'Timestamp Check',
            DispatchList::STATUS_OPEN,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            1,
            1,
            1,
            null,
            $metrics,
            [],
            $created_at,
            $updated_at,
        );

        $this->repository->save($list);

        $model = DispatchListModel::query()->findOrFail($identifier->toInt());

        $this->assertSame($created_at->format('Y-m-d H:i:s'), $model->created_at->format('Y-m-d H:i:s'));
        $this->assertSame($updated_at->format('Y-m-d H:i:s'), $model->updated_at->format('Y-m-d H:i:s'));
    }

    public function test_updates_respect_domain_updated_at(): void
    {
        $identifier = $this->repository->nextListIdentity();
        $metrics = DispatchMetrics::hydrate(1, 1, 0, 1, []);
        $initial_timestamp = new DateTimeImmutable('2024-01-02T08:00:00+00:00');

        $list = DispatchList::hydrate(
            $identifier,
            'REF-'.$identifier->toInt(),
            'Initial',
            DispatchList::STATUS_OPEN,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            1,
            1,
            1,
            null,
            $metrics,
            [],
            $initial_timestamp,
            $initial_timestamp,
        );

        $this->repository->save($list);

        $updated_timestamp = new DateTimeImmutable('2024-01-02T10:15:00+00:00');

        $updated_list = DispatchList::hydrate(
            $identifier,
            $list->reference(),
            $list->title(),
            DispatchList::STATUS_OPEN,
            $list->createdByUserId(),
            $list->closedByUserId(),
            $list->closeRequestedAt(),
            $list->closeRequestedBy(),
            $list->closedAt(),
            $list->exportedAt(),
            $list->exportFilename(),
            $list->totalPackages(),
            $list->totalOrders(),
            $list->totalTruckSlots(),
            $list->notes(),
            $list->metrics(),
            $list->scans(),
            $list->createdAt(),
            $updated_timestamp,
        );

        $this->repository->save($updated_list);

        $model = DispatchListModel::query()->findOrFail($identifier->toInt());

        $this->assertSame($initial_timestamp->format('Y-m-d H:i:s'), $model->created_at->format('Y-m-d H:i:s'));
        $this->assertSame($updated_timestamp->format('Y-m-d H:i:s'), $model->updated_at->format('Y-m-d H:i:s'));
    }
}
