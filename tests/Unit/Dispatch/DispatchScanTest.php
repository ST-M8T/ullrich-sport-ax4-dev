<?php

declare(strict_types=1);

namespace Tests\Unit\Dispatch;

use App\Domain\Dispatch\DispatchScan;
use App\Domain\Shared\ValueObjects\Identifier;
use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DispatchScanTest extends TestCase
{
    public function test_hydrate_trims_barcode_and_preserves_metadata(): void
    {
        $captured_at = (new DateTimeImmutable('2024-02-01T10:15:00+00:00'))->add(new DateInterval('PT3M'));
        $timestamp = new DateTimeImmutable('2024-02-01T10:15:00+00:00');
        $metadata = [' zone ' => 'A', 'weight' => 12.5];

        $scan = DispatchScan::hydrate(
            Identifier::fromInt(42),
            Identifier::fromInt(7),
            '  PKG-99  ',
            Identifier::fromInt(1001),
            Identifier::fromInt(501),
            $captured_at,
            $metadata,
            $timestamp,
            $timestamp
        );

        self::assertSame('PKG-99', $scan->barcode());
        self::assertSame(['zone' => 'A', 'weight' => 12.5], $scan->metadata());
        self::assertSame($captured_at, $scan->capturedAt());
    }

    public function test_hydrate_rejects_blank_barcode(): void
    {
        $timestamp = new DateTimeImmutable('2024-02-01T10:15:00+00:00');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Barcode must be a non-empty string.');

        DispatchScan::hydrate(
            Identifier::fromInt(1),
            Identifier::fromInt(2),
            '   ',
            null,
            null,
            null,
            [],
            $timestamp,
            $timestamp
        );
    }

    public function test_hydrate_rejects_non_string_metadata_keys(): void
    {
        $timestamp = new DateTimeImmutable('2024-02-01T10:15:00+00:00');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Metadata keys must be non-empty strings.');

        DispatchScan::hydrate(
            Identifier::fromInt(1),
            Identifier::fromInt(2),
            'PKG-1',
            null,
            null,
            null,
            ['' => 'empty', 5 => 'numeric'],
            $timestamp,
            $timestamp
        );
    }
}
