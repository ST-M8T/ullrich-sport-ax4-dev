<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Dispatch;

use App\Application\Dispatch\DispatchListService;
use App\Application\Monitoring\AuditLogger;
use App\Application\Monitoring\DomainEventService;
use App\Domain\Dispatch\Contracts\DispatchListRepository;
use App\Domain\Dispatch\DispatchList;
use App\Domain\Dispatch\DispatchMetrics;
use App\Domain\Dispatch\DispatchScan;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class DispatchListServiceTest extends TestCase
{
    private DispatchListRepository&MockInterface $repository;

    private DomainEventService&MockInterface $events;

    private AuditLogger&MockInterface $audit;

    private DispatchListService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(DispatchListRepository::class);
        $this->events = Mockery::mock(DomainEventService::class);
        $this->audit = Mockery::mock(AuditLogger::class);

        $this->service = new DispatchListService($this->repository, $this->events, $this->audit);
    }

    public function test_capture_scan_persists_scan_and_emits_events(): void
    {
        $listId = Identifier::fromInt(10);
        $userId = Identifier::fromInt(7);
        $orderId = Identifier::fromInt(55);
        $list = $this->dispatchList($listId, DispatchList::STATUS_OPEN);
        $nextScanId = Identifier::fromInt(99);

        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->with($listId)
            ->andReturn($list);

        $this->repository
            ->shouldReceive('nextScanIdentity')
            ->once()
            ->andReturn($nextScanId);

        $this->repository
            ->shouldReceive('appendScan')
            ->once()
            ->withArgs(function (DispatchList $updated, DispatchScan $scan) use ($listId, $nextScanId, $orderId): bool {
                $this->assertTrue($updated->id()->equals($listId));
                $this->assertSame(1, $updated->scanCount());
                $this->assertCount(1, $updated->scans());

                $this->assertTrue($scan->id()->equals($nextScanId));
                $this->assertSame('BC-123', $scan->barcode());
                $this->assertSame($orderId->toInt(), $scan->shipmentOrderId()?->toInt());

                $capturedAt = $scan->capturedAt();
                $this->assertInstanceOf(DateTimeImmutable::class, $capturedAt);

                return true;
            })
            ->andReturnUsing(static fn (DispatchList $updated) => $updated);

        $this->events
            ->shouldReceive('record')
            ->once()
            ->with(
                'dispatch.list.scan_captured',
                'dispatch_list',
                (string) $listId->toInt(),
                Mockery::on(function (array $payload): bool {
                    $this->assertSame('BC-123', $payload['barcode']);
                    $this->assertSame(55, $payload['shipment_order_id']);
                    $this->assertArrayHasKey('captured_at', $payload);
                    $this->assertNotEmpty($payload['captured_at']);

                    return true;
                }),
                Mockery::on(function (array $metadata): bool {
                    $this->assertSame(7, $metadata['user_id']);

                    return true;
                })
            );

        $this->audit
            ->shouldReceive('log')
            ->once()
            ->with(
                'dispatch.scan_captured',
                'user',
                (string) $userId->toInt(),
                null,
                Mockery::subset([
                    'dispatch_list_id' => $listId->toInt(),
                    'barcode' => 'BC-123',
                    'scan_count' => 1,
                ])
            );

        $result = $this->service->captureScan($listId, '  BC-123  ', $orderId, $userId, null, ['lane' => 'A1']);

        $this->assertSame(99, $result->id()->toInt());
        $this->assertSame('BC-123', $result->barcode());
        $this->assertSame(['lane' => 'A1'], $result->metadata());
    }

    public function test_capture_scan_throws_when_list_missing(): void
    {
        $listId = Identifier::fromInt(99);

        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->with($listId)
            ->andReturnNull();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Dispatch list not found.');

        $this->service->captureScan($listId, 'BAR', null, null);
    }

    #[DataProvider('provide_closed_statuses')]
    public function test_capture_scan_throws_when_list_closed(string $status): void
    {
        $listId = Identifier::fromInt(4);
        $closedList = $this->dispatchList($listId, $status);

        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->with($listId)
            ->andReturn($closedList);

        $this->repository
            ->shouldNotReceive('nextScanIdentity');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot add scans to a closed dispatch list.');

        $this->service->captureScan($listId, 'BAR', null, null);
    }

    public function test_capture_scan_requires_non_empty_barcode(): void
    {
        $listId = Identifier::fromInt(1);
        $list = $this->dispatchList($listId, DispatchList::STATUS_OPEN);

        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->with($listId)
            ->andReturn($list);

        $this->repository
            ->shouldNotReceive('nextScanIdentity');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Barcode must be a non-empty string.');

        $this->service->captureScan($listId, '   ', null, null);
    }

    /**
     * @return array<int,array{string}>
     */
    public static function provide_closed_statuses(): array
    {
        return [
            [DispatchList::STATUS_CLOSED],
            [DispatchList::STATUS_EXPORTED],
        ];
    }

    public function test_update_metrics_records_event_and_audit(): void
    {
        $listId = Identifier::fromInt(12);
        $list = $this->dispatchList($listId, DispatchList::STATUS_OPEN);
        $metrics = DispatchMetrics::hydrate(5, 6, 7, 8, ['by_sender' => ['x' => 5]]);

        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->with($listId)
            ->andReturn($list);

        $this->repository
            ->shouldReceive('save')
            ->once()
            ->withArgs(function (DispatchList $updated) use ($listId, $metrics): bool {
                $this->assertTrue($updated->id()->equals($listId));
                $this->assertSame($metrics->totalOrders(), $updated->totalOrders());
                $this->assertSame($metrics->totalPackages(), $updated->totalPackages());
                $this->assertSame($metrics->totalTruckSlots(), $updated->totalTruckSlots());
                $this->assertSame($metrics->metrics(), $updated->metrics()->metrics());

                return true;
            });

        $this->events
            ->shouldReceive('record')
            ->once()
            ->with(
                'dispatch.list.metrics_updated',
                'dispatch_list',
                (string) $listId->toInt(),
                Mockery::on(function (array $payload) use ($metrics): bool {
                    $this->assertSame($metrics->totalOrders(), $payload['total_orders']);
                    $this->assertSame($metrics->totalPackages(), $payload['total_packages']);
                    $this->assertSame($metrics->totalItems(), $payload['total_items']);
                    $this->assertSame($metrics->totalTruckSlots(), $payload['total_truck_slots']);
                    $this->assertArrayHasKey('calculated_at', $payload);

                    return true;
                }),
                Mockery::subset(['metrics' => ['by_sender' => ['x' => 5]]])
            );

        $this->audit
            ->shouldReceive('log')
            ->once()
            ->with(
                'dispatch.metrics_updated',
                'system',
                null,
                null,
                Mockery::on(function (array $payload) use ($listId): bool {
                    $this->assertSame($listId->toInt(), $payload['dispatch_list_id']);
                    $this->assertSame(5, $payload['total_orders']);
                    $this->assertSame(6, $payload['total_packages']);
                    $this->assertSame(7, $payload['total_items']);
                    $this->assertSame(8, $payload['total_truck_slots']);
                    $this->assertArrayHasKey('calculated_at', $payload);

                    return true;
                })
            );

        $this->service->updateMetrics($listId, $metrics);
    }

    public function test_update_metrics_throws_when_list_missing(): void
    {
        $listId = Identifier::fromInt(44);
        $metrics = DispatchMetrics::hydrate(1, 2, 3, 4, []);

        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->with($listId)
            ->andReturnNull();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Dispatch list not found.');

        $this->service->updateMetrics($listId, $metrics);
    }

    public function test_close_list_updates_status_and_records_audit(): void
    {
        $listId = Identifier::fromInt(3);
        $userId = Identifier::fromInt(9);
        $list = $this->dispatchList($listId, DispatchList::STATUS_OPEN);

        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->with($listId)
            ->andReturn($list);

        $this->repository
            ->shouldReceive('save')
            ->once()
            ->withArgs(function (DispatchList $updated) use ($listId, $userId): bool {
                $this->assertTrue($updated->id()->equals($listId));
                $this->assertSame('closed', $updated->status());
                $this->assertNotNull($updated->closedAt());
                $this->assertSame($userId->toInt(), $updated->closedByUserId()?->toInt());

                return true;
            });

        $this->events
            ->shouldReceive('record')
            ->once()
            ->with(
                'dispatch.list.closed',
                'dispatch_list',
                (string) $listId->toInt(),
                Mockery::on(fn (array $payload): bool => array_key_exists('closed_at', $payload)),
                Mockery::subset(['closed_by_user_id' => $userId->toInt()])
            );

        $this->audit
            ->shouldReceive('log')
            ->once()
            ->with(
                'dispatch.list_closed',
                'user',
                (string) $userId->toInt(),
                null,
                Mockery::subset(['dispatch_list_id' => $listId->toInt()])
            );

        $closed = $this->service->closeList($listId, $userId, 'export.csv');

        $this->assertSame('closed', $closed->status());
        $this->assertSame('export.csv', $closed->exportFilename());
        $this->assertNull($closed->exportedAt());
    }

    public function test_close_list_without_export_keeps_export_metadata_empty(): void
    {
        $listId = Identifier::fromInt(13);
        $userId = Identifier::fromInt(5);
        $list = $this->dispatchList($listId, DispatchList::STATUS_OPEN);

        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->with($listId)
            ->andReturn($list);

        $this->repository
            ->shouldReceive('save')
            ->once()
            ->withArgs(function (DispatchList $updated) use ($listId, $userId): bool {
                $this->assertTrue($updated->id()->equals($listId));
                $this->assertSame(DispatchList::STATUS_CLOSED, $updated->status());
                $this->assertNull($updated->exportFilename());
                $this->assertNull($updated->exportedAt());
                $this->assertSame($userId->toInt(), $updated->closedByUserId()?->toInt());

                return true;
            });

        $this->events
            ->shouldReceive('record')
            ->once()
            ->with(
                'dispatch.list.closed',
                'dispatch_list',
                (string) $listId->toInt(),
                Mockery::on(function (array $payload): bool {
                    $this->assertArrayHasKey('closed_at', $payload);
                    $this->assertArrayHasKey('export_filename', $payload);
                    $this->assertNull($payload['export_filename']);

                    return true;
                }),
                Mockery::subset(['closed_by_user_id' => $userId->toInt()])
            );

        $this->audit
            ->shouldReceive('log')
            ->once()
            ->with(
                'dispatch.list_closed',
                'user',
                (string) $userId->toInt(),
                null,
                Mockery::subset([
                    'dispatch_list_id' => $listId->toInt(),
                    'export_filename' => null,
                ])
            );

        $closed = $this->service->closeList($listId, $userId);

        $this->assertNull($closed->exportFilename());
        $this->assertNull($closed->exportedAt());
    }

    public function test_export_list_requires_filename(): void
    {
        $listId = Identifier::fromInt(5);
        $userId = Identifier::fromInt(2);
        $list = $this->dispatchList($listId, DispatchList::STATUS_OPEN);

        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->with($listId)
            ->andReturn($list);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Export filename is required.');

        $this->service->exportList($listId, $userId, '  ');
    }

    public function test_export_list_rejects_open_list(): void
    {
        $listId = Identifier::fromInt(8);
        $userId = Identifier::fromInt(4);
        $list = $this->dispatchList($listId, DispatchList::STATUS_OPEN);

        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->with($listId)
            ->andReturn($list);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dispatch list must be closed before it can be exported.');

        $this->service->exportList($listId, $userId, 'export.csv');
    }

    public function test_export_list_updates_export_information(): void
    {
        $listId = Identifier::fromInt(6);
        $userId = Identifier::fromInt(3);
        $createdAt = new DateTimeImmutable('-2 hours');
        $closedAt = new DateTimeImmutable('-1 hour');
        $list = DispatchList::hydrate(
            $listId,
            'LIST-'.$listId->toInt(),
            'Example List',
            DispatchList::STATUS_CLOSED,
            null,
            $userId,
            null,
            null,
            $closedAt,
            null,
            null,
            null,
            null,
            null,
            null,
            DispatchMetrics::hydrate(0, 0, 0, 0, []),
            [],
            $createdAt,
            $closedAt,
        );

        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->with($listId)
            ->andReturn($list);

        $this->repository
            ->shouldReceive('save')
            ->once()
            ->withArgs(function (DispatchList $updated) use ($userId): bool {
                $this->assertSame('exported', $updated->status());
                $this->assertSame('export.csv', $updated->exportFilename());
                $this->assertNotNull($updated->exportedAt());
                $this->assertSame($userId->toInt(), $updated->closedByUserId()?->toInt());

                return true;
            });

        $this->events
            ->shouldReceive('record')
            ->once()
            ->with(
                'dispatch.list.exported',
                'dispatch_list',
                (string) $listId->toInt(),
                Mockery::subset(['export_filename' => 'export.csv']),
                Mockery::subset(['exported_by_user_id' => $userId->toInt()])
            );

        $this->audit
            ->shouldReceive('log')
            ->once()
            ->with(
                'dispatch.list_exported',
                'user',
                (string) $userId->toInt(),
                null,
                Mockery::subset(['dispatch_list_id' => $listId->toInt()])
            );

        $exported = $this->service->exportList($listId, $userId, 'export.csv');

        $this->assertSame('exported', $exported->status());
        $this->assertSame('export.csv', $exported->exportFilename());
    }

    private function dispatchList(Identifier $id, string $status): DispatchList
    {
        $createdAt = new DateTimeImmutable('-2 hours');
        $closedAt = null;
        $closedBy = null;
        $exportedAt = null;
        $exportFilename = null;
        $updatedAt = new DateTimeImmutable('-1 hour');

        if ($status !== DispatchList::STATUS_OPEN) {
            $closedAt = new DateTimeImmutable('-90 minutes');
            $closedBy = Identifier::fromInt(max(1, $id->toInt() - 1));
            $updatedAt = $closedAt;
        }

        if ($status === DispatchList::STATUS_EXPORTED) {
            $exportedAt = new DateTimeImmutable('-30 minutes');
            $exportFilename = sprintf('export-%d.csv', $id->toInt());
            $updatedAt = $exportedAt;
        }

        return DispatchList::hydrate(
            $id,
            'LIST-'.$id->toInt(),
            'Example List',
            $status,
            null,
            $closedBy,
            null,
            null,
            $closedAt,
            $exportedAt,
            $exportFilename,
            null,
            null,
            null,
            null,
            DispatchMetrics::hydrate(0, 0, 0, 0, []),
            [],
            $createdAt,
            $updatedAt,
        );
    }
}
