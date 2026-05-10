<?php

declare(strict_types=1);

namespace App\Domain\Dispatch;

use InvalidArgumentException;

final class DispatchListPaginationResult
{
    /**
     * @var array<int,DispatchList>
     */
    public readonly array $lists;

    /**
     * @param  array<int,DispatchList>  $lists
     */
    public function __construct(
        array $lists,
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $total,
    ) {
        if ($page < 1) {
            throw new InvalidArgumentException('Page must be at least 1.');
        }

        if ($perPage < 1) {
            throw new InvalidArgumentException('Items per page must be at least 1.');
        }

        if ($total < 0) {
            throw new InvalidArgumentException('Total items must be a non-negative integer.');
        }

        $this->lists = self::sanitize_lists($lists);

        $list_count = count($this->lists);

        if ($list_count > $perPage) {
            throw new InvalidArgumentException('List count cannot exceed the items per page.');
        }

        if ($list_count > $total) {
            throw new InvalidArgumentException('List count cannot exceed the reported total items.');
        }
    }

    public function totalPages(): int
    {
        return (int) max(1, ceil($this->total / max(1, $this->perPage)));
    }

    public function hasMorePages(): bool
    {
        return $this->page < $this->totalPages();
    }

    /**
     * @param  array<int,DispatchList>  $lists
     * @return array<int,DispatchList>
     */
    private static function sanitize_lists(array $lists): array
    {
        $normalized = array_values($lists);

        foreach ($normalized as $list) {
            if (! $list instanceof DispatchList) {
                throw new InvalidArgumentException('List entries must be instances of DispatchList.');
            }
        }

        return $normalized;
    }
}
