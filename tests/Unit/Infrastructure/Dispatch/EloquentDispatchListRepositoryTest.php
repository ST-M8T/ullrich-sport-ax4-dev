<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Dispatch;

use App\Domain\Dispatch\Contracts\DispatchListRepository;
use App\Domain\Dispatch\DispatchList;
use App\Domain\Dispatch\DispatchScan;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Infrastructure\Persistence\Dispatch\Eloquent\DispatchListModel;
use App\Infrastructure\Persistence\Dispatch\Eloquent\DispatchScanModel;
use App\Infrastructure\Persistence\Dispatch\Eloquent\DispatchSequenceModel;
use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class EloquentDispatchListRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_by_id_hydrates_when_metrics_missing(): void
    {
        $repository = app(DispatchListRepository::class);

        $model = DispatchListModel::factory()->create([
            'status' => 'closed',
            'closed_by_user_id' => UserModel::factory(),
            'closed_at' => now(),
            'updated_at' => now(),
            'total_orders' => 7,
            'total_packages' => 5,
            'total_truck_slots' => 3,
        ]);

        $list = $repository->getById(Identifier::fromInt((int) $model->getKey()));

        $this->assertNotNull($list);
        $this->assertSame('closed', $list->status());
        $this->assertSame(7, $list->metrics()->totalOrders());
        $this->assertSame(5, $list->metrics()->totalPackages());
        $this->assertSame(3, $list->metrics()->totalTruckSlots());
        $this->assertSame(0, $list->metrics()->totalItems());
        $this->assertSame([], $list->metrics()->metrics());
    }

    public function test_get_by_id_coalesces_null_totals_to_zero(): void
    {
        $repository = app(DispatchListRepository::class);

        $model = DispatchListModel::factory()->create([
            'total_orders' => null,
            'total_packages' => null,
            'total_truck_slots' => null,
        ]);

        $list = $repository->getById(Identifier::fromInt((int) $model->getKey()));

        $this->assertNotNull($list);
        $this->assertSame(0, $list->totalOrders());
        $this->assertSame(0, $list->totalPackages());
        $this->assertSame(0, $list->totalTruckSlots());
        $this->assertSame(0, $list->metrics()->totalOrders());
        $this->assertSame(0, $list->metrics()->totalPackages());
        $this->assertSame(0, $list->metrics()->totalTruckSlots());
    }

    public function test_append_scan_persists_sanitized_scan_data(): void
    {
        $repository = app(DispatchListRepository::class);
        $dispatch_list = DispatchListModel::factory()->create([
            'status' => 'open',
            'closed_at' => null,
            'closed_by_user_id' => null,
            'exported_at' => null,
            'export_filename' => null,
            'updated_at' => now(),
        ]);
        $aggregate = $repository->getById(Identifier::fromInt((int) $dispatch_list->getKey()));
        $this->assertInstanceOf(DispatchList::class, $aggregate);

        $scanId = $repository->nextScanIdentity();
        $timestamp = new DateTimeImmutable('2024-01-01T10:00:00+00:00');

        $scan = DispatchScan::hydrate(
            $scanId,
            $aggregate->id(),
            '  PKG-200  ',
            null,
            null,
            $timestamp,
            [' zone ' => 'A', 'weight' => 10],
            $timestamp,
            $timestamp,
        );

        $updatedList = $aggregate->recordScan($scan, $timestamp);
        $persisted = $repository->appendScan($updatedList, $scan);

        $this->assertSame($updatedList->scanCount(), $persisted->scanCount());

        $this->assertDatabaseHas('dispatch_scans', [
            'id' => $scanId->toInt(),
            'dispatch_list_id' => $dispatch_list->getKey(),
            'barcode' => 'PKG-200',
        ]);

        $stored_metadata = DispatchScanModel::query()->findOrFail($scanId->toInt())->metadata;
        $this->assertSame(['zone' => 'A', 'weight' => 10], $stored_metadata);
    }

    public function test_append_scan_rejects_when_list_was_closed_in_between(): void
    {
        $repository = app(DispatchListRepository::class);
        $dispatch_list = DispatchListModel::factory()->create([
            'status' => 'open',
            'closed_at' => null,
            'closed_by_user_id' => null,
            'exported_at' => null,
            'export_filename' => null,
            'updated_at' => now(),
        ]);
        $aggregate = $repository->getById(Identifier::fromInt((int) $dispatch_list->getKey()));
        $this->assertInstanceOf(DispatchList::class, $aggregate);

        $scanId = $repository->nextScanIdentity();
        $timestamp = new DateTimeImmutable('2024-01-01T12:00:00+00:00');

        $scan = DispatchScan::hydrate(
            $scanId,
            $aggregate->id(),
            'PKG-LOCK',
            null,
            null,
            $timestamp,
            [],
            $timestamp,
            $timestamp,
        );

        $updatedList = $aggregate->recordScan($scan, $timestamp);

        DispatchListModel::query()
            ->whereKey($dispatch_list->getKey())
            ->update(['status' => 'closed']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Dispatch list is closed.');

        $repository->appendScan($updatedList, $scan);
    }

    public function test_next_list_identity_advances_from_existing_records(): void
    {
        $repository = app(DispatchListRepository::class);

        DispatchSequenceModel::query()
            ->where('sequence_name', DispatchSequenceModel::LIST_SEQUENCE)
            ->update(['next_id' => 11]);

        $first = $repository->nextListIdentity();
        $second = $repository->nextListIdentity();

        $this->assertSame(11, $first->toInt());
        $this->assertSame(12, $second->toInt());
    }

    public function test_next_scan_identity_advances_from_existing_records(): void
    {
        $repository = app(DispatchListRepository::class);

        DispatchSequenceModel::query()
            ->where('sequence_name', DispatchSequenceModel::SCAN_SEQUENCE)
            ->update(['next_id' => 8]);

        $first = $repository->nextScanIdentity();
        $second = $repository->nextScanIdentity();

        $this->assertSame(8, $first->toInt());
        $this->assertSame(9, $second->toInt());
    }

    public function test_next_list_identity_syncs_with_max_dispatch_list_id(): void
    {
        $repository = app(DispatchListRepository::class);

        DispatchListModel::factory()->count(3)->create();

        $current_max = (int) DispatchListModel::query()->max('id');

        DispatchSequenceModel::query()
            ->where('sequence_name', DispatchSequenceModel::LIST_SEQUENCE)
            ->update(['next_id' => 1]);

        $next = $repository->nextListIdentity();

        $this->assertSame($current_max + 1, $next->toInt());
    }

    public function test_next_scan_identity_syncs_with_max_dispatch_scan_id(): void
    {
        $repository = app(DispatchListRepository::class);

        DispatchScanModel::factory()->count(2)->create([
            'shipment_order_id' => null,
            'captured_by_user_id' => null,
        ]);

        $current_max = (int) DispatchScanModel::query()->max('id');

        DispatchSequenceModel::query()
            ->where('sequence_name', DispatchSequenceModel::SCAN_SEQUENCE)
            ->update(['next_id' => 1]);

        $next = $repository->nextScanIdentity();

        $this->assertSame($current_max + 1, $next->toInt());
    }

    public function test_next_list_identity_skips_ids_created_outside_repository(): void
    {
        $repository = app(DispatchListRepository::class);

        $external = DispatchListModel::factory()->create();

        DispatchSequenceModel::query()
            ->where('sequence_name', DispatchSequenceModel::LIST_SEQUENCE)
            ->update(['next_id' => $external->getKey()]);

        $next = $repository->nextListIdentity();

        $this->assertSame($external->getKey() + 1, $next->toInt());
    }

    public function test_next_scan_identity_skips_ids_created_outside_repository(): void
    {
        $repository = app(DispatchListRepository::class);

        $list = DispatchListModel::factory()->create();
        $external = DispatchScanModel::factory()->create([
            'dispatch_list_id' => $list->getKey(),
            'shipment_order_id' => null,
            'captured_by_user_id' => null,
        ]);

        DispatchSequenceModel::query()
            ->where('sequence_name', DispatchSequenceModel::SCAN_SEQUENCE)
            ->update(['next_id' => $external->getKey()]);

        $next = $repository->nextScanIdentity();

        $this->assertSame($external->getKey() + 1, $next->toInt());
    }
}
