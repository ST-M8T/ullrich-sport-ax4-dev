<?php

declare(strict_types=1);

namespace Tests\Unit\Dispatch;

use App\Domain\Dispatch\DispatchList;
use App\Domain\Dispatch\DispatchListPaginationResult;
use App\Domain\Dispatch\DispatchMetrics;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DispatchListPaginationResultTest extends TestCase
{
    public function test_it_exposes_pagination_information(): void
    {
        $lists = [
            $this->make_dispatch_list(1),
            $this->make_dispatch_list(2),
        ];

        $result = new DispatchListPaginationResult($lists, 2, 2, 5);

        self::assertSame(2, $result->page);
        self::assertSame(2, $result->perPage);
        self::assertSame(5, $result->total);
        self::assertSame(3, $result->totalPages());
        self::assertTrue($result->hasMorePages());
        self::assertCount(2, $result->lists);
        self::assertSame(1, $result->lists[0]->id()->toInt());
    }

    public function test_it_rejects_page_less_than_one(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Page must be at least 1.');

        new DispatchListPaginationResult([], 0, 10, 0);
    }

    public function test_it_rejects_per_page_less_than_one(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Items per page must be at least 1.');

        new DispatchListPaginationResult([], 1, 0, 0);
    }

    public function test_it_rejects_negative_total(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Total items must be a non-negative integer.');

        new DispatchListPaginationResult([], 1, 10, -1);
    }

    public function test_it_rejects_when_list_count_exceeds_per_page(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('List count cannot exceed the items per page.');

        new DispatchListPaginationResult(
            [
                $this->make_dispatch_list(1),
                $this->make_dispatch_list(2),
            ],
            1,
            1,
            2
        );
    }

    public function test_it_rejects_when_list_count_exceeds_total(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('List count cannot exceed the reported total items.');

        new DispatchListPaginationResult(
            [
                $this->make_dispatch_list(1),
                $this->make_dispatch_list(2),
            ],
            1,
            5,
            1
        );
    }

    public function test_it_rejects_non_dispatch_list_entries(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('List entries must be instances of DispatchList.');

        new DispatchListPaginationResult(['invalid'], 1, 10, 10);
    }

    private function make_dispatch_list(int $id): DispatchList
    {
        $timestamp = new DateTimeImmutable('2024-01-01T00:00:00+00:00');

        return DispatchList::hydrate(
            Identifier::fromInt($id),
            'REF-'.$id,
            'List '.$id,
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
            DispatchMetrics::hydrate(0, 0, 0, 0, []),
            [],
            $timestamp,
            $timestamp
        );
    }
}
