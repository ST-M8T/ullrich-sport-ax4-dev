<?php

declare(strict_types=1);

namespace Tests\Unit\Dispatch;

use App\Domain\Dispatch\DispatchList;
use App\Domain\Dispatch\DispatchMetrics;
use App\Domain\Dispatch\DispatchScan;
use App\Domain\Shared\ValueObjects\Identifier;
use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class DispatchListTest extends TestCase
{
    public function test_hydrate_trims_scalar_fields_and_exposes_state(): void
    {
        $timestamp = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $metrics = DispatchMetrics::hydrate(10, 20, 30, 5, ['load_factor' => 0.75]);
        $closedAt = $timestamp->add(new DateInterval('PT5M'));
        $exportedAt = $timestamp->add(new DateInterval('PT10M'));
        $scanTimestamp = $timestamp->add(new DateInterval('PT5M'));
        $scan = DispatchScan::hydrate(
            Identifier::fromInt(2),
            Identifier::fromInt(1),
            '  PKG-001  ',
            Identifier::fromInt(11),
            Identifier::fromInt(12),
            $scanTimestamp,
            ['zone' => 'A'],
            $scanTimestamp,
            $scanTimestamp
        );

        $list = DispatchList::hydrate(
            Identifier::fromInt(1),
            '  REF-123  ',
            '  Early Truck  ',
            DispatchList::STATUS_EXPORTED.' ',
            Identifier::fromInt(21),
            Identifier::fromInt(22),
            $timestamp->add(new DateInterval('PT2M')),
            '  shift-supervisor ',
            $closedAt,
            $exportedAt,
            '  export.csv ',
            40,
            25,
            6,
            '  Handle with care ',
            $metrics,
            [$scan],
            $timestamp,
            $exportedAt
        );

        self::assertSame('REF-123', $list->reference());
        self::assertSame('Early Truck', $list->title());
        self::assertSame(DispatchList::STATUS_EXPORTED, $list->status());
        self::assertSame('shift-supervisor', $list->closeRequestedBy());
        self::assertSame('export.csv', $list->exportFilename());
        self::assertSame('Handle with care', $list->notes());
        self::assertTrue($list->isClosed());
        self::assertFalse($list->canAddScans());
        self::assertSame($closedAt, $list->closedAt());
        self::assertSame($exportedAt, $list->exportedAt());
        self::assertSame([$scan], $list->scans());
        self::assertSame($metrics, $list->metrics());
        self::assertSame(1, $list->scanCount());
    }

    public function test_hydrate_converts_blank_strings_to_null(): void
    {
        $timestamp = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $metrics = DispatchMetrics::hydrate(0, 0, 0, 0, []);

        $list = DispatchList::hydrate(
            Identifier::fromInt(1),
            '   ',
            '',
            DispatchList::STATUS_OPEN,
            null,
            null,
            null,
            '   ',
            null,
            null,
            '   ',
            null,
            null,
            null,
            '    ',
            $metrics,
            [],
            $timestamp,
            $timestamp->add(new DateInterval('PT2H'))
        );

        self::assertNull($list->reference());
        self::assertNull($list->title());
        self::assertNull($list->closeRequestedBy());
        self::assertNull($list->exportFilename());
        self::assertNull($list->notes());
        self::assertSame(0, $list->scanCount());
    }

    public function test_status_helpers_reflect_closed_and_exported_lists(): void
    {
        $timestamp = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $metrics = DispatchMetrics::hydrate(0, 0, 0, 0, []);

        $closed = DispatchList::hydrate(
            Identifier::fromInt(1),
            null,
            null,
            DispatchList::STATUS_CLOSED,
            null,
            Identifier::fromInt(9),
            $timestamp->sub(new DateInterval('PT1H')),
            'supervisor',
            $timestamp,
            null,
            null,
            0,
            0,
            0,
            null,
            $metrics,
            [],
            $timestamp->sub(new DateInterval('P1D')),
            $timestamp
        );

        $exported = DispatchList::hydrate(
            Identifier::fromInt(2),
            null,
            null,
            DispatchList::STATUS_EXPORTED,
            null,
            Identifier::fromInt(10),
            $timestamp->sub(new DateInterval('PT2H')),
            'supervisor',
            $timestamp->sub(new DateInterval('PT90M')),
            $timestamp,
            'dispatch.csv',
            0,
            0,
            0,
            null,
            $metrics,
            [],
            $timestamp->sub(new DateInterval('P2D')),
            $timestamp
        );

        self::assertTrue($closed->isClosed());
        self::assertFalse($closed->canAddScans());
        self::assertTrue($exported->isClosed());
        self::assertFalse($exported->canAddScans());
    }

    public function test_hydrate_rejects_unknown_status(): void
    {
        $timestamp = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $metrics = DispatchMetrics::hydrate(0, 0, 0, 0, []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid dispatch list status "invalid".');

        DispatchList::hydrate(
            Identifier::fromInt(1),
            null,
            null,
            'invalid',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $metrics,
            [],
            $timestamp,
            $timestamp->add(new DateInterval('PT2H'))
        );
    }

    public function test_hydrate_rejects_updated_at_before_created_at(): void
    {
        $created_at = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $updated_at = $created_at->sub(new DateInterval('PT1H'));
        $metrics = DispatchMetrics::hydrate(0, 0, 0, 0, []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('updated_at must be greater than or equal to created_at.');

        DispatchList::hydrate(
            Identifier::fromInt(1),
            null,
            null,
            DispatchList::STATUS_OPEN,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $metrics,
            [],
            $created_at,
            $updated_at
        );
    }

    /**
     * @return iterable<string,array{0:string}>
     */
    public static function negative_total_field_provider(): iterable
    {
        yield 'packages' => ['total_packages'];
        yield 'orders' => ['total_orders'];
        yield 'truck_slots' => ['total_truck_slots'];
    }

    #[DataProvider('negative_total_field_provider')]
    public function test_hydrate_rejects_negative_totals(string $field): void
    {
        $timestamp = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $metrics = DispatchMetrics::hydrate(0, 0, 0, 0, []);
        $total_packages = null;
        $total_orders = null;
        $total_truck_slots = null;

        match ($field) {
            'total_packages' => $total_packages = -1,
            'total_orders' => $total_orders = -1,
            'total_truck_slots' => $total_truck_slots = -1,
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('%s must be greater than or equal to zero.', $field));

        DispatchList::hydrate(
            Identifier::fromInt(1),
            null,
            null,
            DispatchList::STATUS_OPEN,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $total_packages,
            $total_orders,
            $total_truck_slots,
            null,
            $metrics,
            [],
            $timestamp,
            $timestamp->add(new DateInterval('PT2H'))
        );
    }

    public function test_hydrate_rejects_non_dispatch_scans(): void
    {
        $timestamp = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $metrics = DispatchMetrics::hydrate(0, 0, 0, 0, []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Scans must be instances of DispatchScan.');

        DispatchList::hydrate(
            Identifier::fromInt(1),
            null,
            null,
            DispatchList::STATUS_OPEN,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $metrics,
            [new stdClass],
            $timestamp,
            $timestamp
        );
    }

    public function test_close_transitions_status_and_sets_metadata(): void
    {
        $timestamp = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $metrics = DispatchMetrics::hydrate(0, 0, 0, 0, []);
        $list = DispatchList::hydrate(
            Identifier::fromInt(1),
            null,
            null,
            DispatchList::STATUS_OPEN,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $metrics,
            [],
            $timestamp,
            $timestamp->add(new DateInterval('PT2H'))
        );

        $closedAt = $timestamp->add(new DateInterval('PT1H'));
        $userId = Identifier::fromInt(5);

        $closed = $list->close($userId, '  export.csv ', $closedAt);

        self::assertSame(DispatchList::STATUS_CLOSED, $closed->status());
        self::assertSame($userId->toInt(), $closed->closedByUserId()?->toInt());
        self::assertSame($closedAt, $closed->closedAt());
        self::assertSame('export.csv', $closed->exportFilename());
        self::assertSame($closedAt, $closed->updatedAt());
        self::assertSame(0, $closed->scanCount());
    }

    public function test_export_transitions_status_and_preserves_close_metadata(): void
    {
        $timestamp = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $closedAt = $timestamp->add(new DateInterval('PT30M'));
        $metrics = DispatchMetrics::hydrate(0, 0, 0, 0, []);
        $closedBy = Identifier::fromInt(5);

        $list = DispatchList::hydrate(
            Identifier::fromInt(1),
            null,
            null,
            DispatchList::STATUS_CLOSED,
            null,
            $closedBy,
            null,
            null,
            $closedAt,
            null,
            null,
            null,
            null,
            null,
            null,
            $metrics,
            [],
            $timestamp,
            $closedAt
        );

        $exportedAt = $timestamp->add(new DateInterval('PT2H'));
        $userId = Identifier::fromInt(7);

        $exported = $list->export($userId, 'final.csv', $exportedAt);

        self::assertSame(DispatchList::STATUS_EXPORTED, $exported->status());
        self::assertSame('final.csv', $exported->exportFilename());
        self::assertSame($exportedAt, $exported->exportedAt());
        self::assertSame($exportedAt, $exported->updatedAt());
        self::assertSame($closedBy->toInt(), $exported->closedByUserId()?->toInt());
        self::assertSame($closedAt, $exported->closedAt());
        self::assertSame(0, $exported->scanCount());
    }

    public function test_hydrate_rejects_scan_count_mismatch(): void
    {
        $timestamp = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $metrics = DispatchMetrics::hydrate(0, 0, 0, 0, []);
        $scan = DispatchScan::hydrate(
            Identifier::fromInt(2),
            Identifier::fromInt(1),
            'PKG-1',
            null,
            null,
            $timestamp,
            [],
            $timestamp,
            $timestamp,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('scan_count must match provided scans.');

        DispatchList::hydrate(
            Identifier::fromInt(1),
            null,
            null,
            DispatchList::STATUS_OPEN,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $metrics,
            [$scan],
            $timestamp,
            $timestamp,
            0
        );
    }

    public function test_export_requires_closed_status(): void
    {
        $timestamp = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $metrics = DispatchMetrics::hydrate(0, 0, 0, 0, []);
        $list = DispatchList::hydrate(
            Identifier::fromInt(1),
            null,
            null,
            DispatchList::STATUS_OPEN,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $metrics,
            [],
            $timestamp,
            $timestamp->add(new DateInterval('PT2H'))
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dispatch list must be closed before it can be exported.');

        $list->export(Identifier::fromInt(7), 'final.csv', $timestamp->add(new DateInterval('PT1H')));
    }

    public function test_export_requires_csv_filename(): void
    {
        $timestamp = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $closedAt = $timestamp->add(new DateInterval('PT15M'));
        $metrics = DispatchMetrics::hydrate(0, 0, 0, 0, []);
        $list = DispatchList::hydrate(
            Identifier::fromInt(1),
            null,
            null,
            DispatchList::STATUS_CLOSED,
            null,
            Identifier::fromInt(5),
            null,
            null,
            $closedAt,
            null,
            null,
            null,
            null,
            null,
            null,
            $metrics,
            [],
            $timestamp,
            $closedAt
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('export_filename must end with ".csv".');

        $list->export(Identifier::fromInt(7), 'final.txt', $timestamp->add(new DateInterval('PT30M')));
    }

    public function test_open_status_rejects_closed_state(): void
    {
        $timestamp = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $metrics = DispatchMetrics::hydrate(0, 0, 0, 0, []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Open dispatch lists cannot be closed or exported.');

        DispatchList::hydrate(
            Identifier::fromInt(1),
            null,
            null,
            DispatchList::STATUS_OPEN,
            null,
            Identifier::fromInt(9),
            null,
            null,
            $timestamp,
            null,
            null,
            null,
            null,
            null,
            null,
            $metrics,
            [],
            $timestamp,
            $timestamp
        );
    }

    public function test_closed_status_without_metadata_falls_back_to_open(): void
    {
        // Domain-Verhalten: Legacy-Daten ohne `closed_at`/`closed_by_user_id`
        // werden in den OPEN-Status normalisiert (siehe DispatchList::normalizeStatusForPersistence).
        // Die strikte Invariante greift erst nach dieser Normalisierung — hier prüfen wir
        // explizit den Toleranz-Pfad für historische Datensätze.
        $timestamp = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $metrics = DispatchMetrics::hydrate(0, 0, 0, 0, []);

        $list = DispatchList::hydrate(
            Identifier::fromInt(1),
            null,
            null,
            DispatchList::STATUS_CLOSED,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $metrics,
            [],
            $timestamp,
            $timestamp
        );

        self::assertSame(DispatchList::STATUS_OPEN, $list->status());
        self::assertNull($list->closedAt());
        self::assertNull($list->closedByUserId());
    }

    public function test_exported_status_without_full_metadata_falls_back(): void
    {
        // Domain-Verhalten: Bei STATUS_EXPORTED ohne vollständige Export-Metadaten
        // (closed_at, closed_by_user_id, exported_at, export_filename) fällt das
        // Aggregat auf den nächst-niedrigen gültigen Status zurück (CLOSED, sonst OPEN).
        $timestamp = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $metrics = DispatchMetrics::hydrate(0, 0, 0, 0, []);

        $list = DispatchList::hydrate(
            Identifier::fromInt(1),
            null,
            null,
            DispatchList::STATUS_EXPORTED,
            null,
            Identifier::fromInt(8),
            null,
            null,
            $timestamp,
            null,
            null,
            null,
            null,
            null,
            null,
            $metrics,
            [],
            $timestamp,
            $timestamp->add(new DateInterval('PT1H'))
        );

        self::assertSame(DispatchList::STATUS_CLOSED, $list->status());
        self::assertNull($list->exportedAt());
        self::assertNull($list->exportFilename());
    }

    public function test_hydrate_rejects_export_filename_without_csv_extension(): void
    {
        $timestamp = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $metrics = DispatchMetrics::hydrate(0, 0, 0, 0, []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('export_filename must end with ".csv".');

        DispatchList::hydrate(
            Identifier::fromInt(1),
            null,
            null,
            DispatchList::STATUS_EXPORTED,
            null,
            Identifier::fromInt(4),
            null,
            null,
            $timestamp,
            $timestamp,
            'report.txt',
            null,
            null,
            null,
            null,
            $metrics,
            [],
            $timestamp,
            $timestamp
        );
    }

    public function test_exported_at_must_not_precede_closed_at(): void
    {
        $createdAt = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $closedAt = new DateTimeImmutable('2024-02-01T11:15:00+00:00');
        $exportedAt = new DateTimeImmutable('2024-02-01T10:45:00+00:00');
        $metrics = DispatchMetrics::hydrate(0, 0, 0, 0, []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exported_at must be greater than or equal to closed_at.');

        DispatchList::hydrate(
            Identifier::fromInt(1),
            null,
            null,
            DispatchList::STATUS_EXPORTED,
            null,
            Identifier::fromInt(4),
            null,
            null,
            $closedAt,
            $exportedAt,
            'export.csv',
            null,
            null,
            null,
            null,
            $metrics,
            [],
            $createdAt,
            $closedAt
        );
    }

    public function test_close_requested_at_cannot_precede_creation(): void
    {
        $createdAt = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $metrics = DispatchMetrics::hydrate(0, 0, 0, 0, []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('close_requested_at must be greater than or equal to created_at.');

        DispatchList::hydrate(
            Identifier::fromInt(1),
            null,
            null,
            DispatchList::STATUS_OPEN,
            Identifier::fromInt(3),
            null,
            $createdAt->sub(new DateInterval('PT1H')),
            'supervisor',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $metrics,
            [],
            $createdAt,
            $createdAt
        );
    }

    public function test_closed_at_cannot_exceed_updated_at(): void
    {
        $createdAt = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $updatedAt = $createdAt->add(new DateInterval('PT30M'));
        $metrics = DispatchMetrics::hydrate(0, 0, 0, 0, []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('closed_at must be less than or equal to updated_at.');

        DispatchList::hydrate(
            Identifier::fromInt(1),
            null,
            null,
            DispatchList::STATUS_CLOSED,
            Identifier::fromInt(2),
            Identifier::fromInt(5),
            null,
            'supervisor',
            $updatedAt->add(new DateInterval('PT5M')),
            null,
            null,
            null,
            null,
            null,
            null,
            $metrics,
            [],
            $createdAt,
            $updatedAt
        );
    }

    public function test_record_scan_appends_scan_and_updates_state(): void
    {
        $createdAt = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $updatedAt = $createdAt->add(new DateInterval('PT1H'));
        $metrics = DispatchMetrics::hydrate(0, 0, 0, 0, []);
        $list = DispatchList::hydrate(
            Identifier::fromInt(1),
            'REF-123',
            'Morning load',
            DispatchList::STATUS_OPEN,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $metrics,
            [],
            $createdAt,
            $createdAt
        );

        $scan = DispatchScan::hydrate(
            Identifier::fromInt(10),
            $list->id(),
            'PKG-1',
            null,
            null,
            $updatedAt,
            [],
            $updatedAt,
            $updatedAt
        );

        $recorded = $list->recordScan($scan);

        self::assertSame(1, $recorded->scanCount());
        self::assertCount(1, $recorded->scans());
        self::assertSame($scan, $recorded->scans()[0]);
        self::assertSame($updatedAt, $recorded->updatedAt());
    }

    public function test_record_scan_rejects_when_list_closed(): void
    {
        $timestamp = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $metrics = DispatchMetrics::hydrate(0, 0, 0, 0, []);
        $list = DispatchList::hydrate(
            Identifier::fromInt(1),
            null,
            null,
            DispatchList::STATUS_CLOSED,
            null,
            Identifier::fromInt(9),
            null,
            null,
            $timestamp,
            null,
            null,
            null,
            null,
            null,
            null,
            $metrics,
            [],
            $timestamp,
            $timestamp
        );

        $scan = DispatchScan::hydrate(
            Identifier::fromInt(2),
            $list->id(),
            'PKG-1',
            null,
            null,
            $timestamp,
            [],
            $timestamp,
            $timestamp
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot add scans to a closed dispatch list.');

        $list->recordScan($scan);
    }

    public function test_record_scan_rejects_when_scan_belongs_to_other_list(): void
    {
        $timestamp = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $metrics = DispatchMetrics::hydrate(0, 0, 0, 0, []);
        $list = DispatchList::hydrate(
            Identifier::fromInt(1),
            null,
            null,
            DispatchList::STATUS_OPEN,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $metrics,
            [],
            $timestamp,
            $timestamp
        );

        $scan = DispatchScan::hydrate(
            Identifier::fromInt(2),
            Identifier::fromInt(99),
            'PKG-1',
            null,
            null,
            $timestamp,
            [],
            $timestamp,
            $timestamp
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Scan must reference the same dispatch list.');

        $list->recordScan($scan);
    }
}
