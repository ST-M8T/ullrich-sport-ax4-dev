<?php

namespace App\Application\Configuration\Queries;

use App\Domain\Configuration\Contracts\NotificationRepository;
use App\Domain\Configuration\NotificationMessage;

final class ListNotifications
{
    public function __construct(private readonly NotificationRepository $notifications) {}

    /**
     * @param  array<string,mixed>  $filters
     * @return array<int,NotificationMessage>
     */
    public function __invoke(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        return $this->normalizeIterable(
            $this->notifications->search($this->normalizeFilters($filters), max(1, $limit), max(0, $offset))
        );
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        $normalized = [];

        if (isset($filters['status'])) {
            $status = strtolower(trim((string) $filters['status']));
            if ($status !== '') {
                $normalized['status'] = $status;
            }
        }

        if (isset($filters['notification_type'])) {
            $type = trim((string) $filters['notification_type']);
            if ($type !== '') {
                $normalized['notification_type'] = $type;
            }
        }

        return $normalized;
    }

    /**
     * @template T
     *
     * @param  iterable<T>  $items
     * @return array<int,T>
     */
    private function normalizeIterable(iterable $items): array
    {
        return is_array($items) ? array_values($items) : iterator_to_array($items, false);
    }
}
