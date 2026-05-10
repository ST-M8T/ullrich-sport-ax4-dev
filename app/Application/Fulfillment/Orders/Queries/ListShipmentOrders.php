<?php

namespace App\Application\Fulfillment\Orders\Queries;

use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Fulfillment\Orders\ShipmentOrderPaginationResult;
use DateTimeInterface;
use Illuminate\Support\Arr;

final class ListShipmentOrders
{
    public function __construct(private readonly ShipmentOrderRepository $orders) {}

    /**
     * @param  array<string,mixed>  $filters
     */
    public function __invoke(int $page = 1, int $perPage = 25, array $filters = []): ShipmentOrderPaginationResult
    {
        $normalized = $this->normaliseFilters($filters);

        return $this->orders->paginate($page, $perPage, $normalized);
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    private function normaliseFilters(array $filters): array
    {
        $filter = $filters['filter'] ?? null;
        if (! is_string($filter) || $filter === '') {
            $filter = null;
        } else {
            $filter = strtolower($filter);
            if (! in_array($filter, ['recent', 'booked', 'unbooked'], true)) {
                $filter = null;
            }
        }

        $search = $filters['search'] ?? null;
        if (is_string($search)) {
            $search = trim($search);
            $search = $search === '' ? null : $search;
        } else {
            $search = null;
        }

        $direction = strtolower((string) ($filters['direction'] ?? ''));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = null;
        }

        $processedFrom = $filters['processed_from'] ?? null;
        $processedFrom = $processedFrom instanceof DateTimeInterface ? $processedFrom : null;

        $processedTo = $filters['processed_to'] ?? null;
        $processedTo = $processedTo instanceof DateTimeInterface ? $processedTo : null;

        $senderCode = Arr::get($filters, 'sender_code');
        $senderCode = is_string($senderCode) ? trim($senderCode) : null;
        $senderCode = $senderCode === '' ? null : $senderCode;

        $destinationCountry = Arr::get($filters, 'destination_country');
        $destinationCountry = is_string($destinationCountry) ? trim($destinationCountry) : null;
        $destinationCountry = $destinationCountry === '' ? null : $destinationCountry;

        [$sortColumn, $sortDirection] = $this->resolveSort(
            is_string($filters['sort'] ?? null) ? strtolower((string) $filters['sort']) : null,
            $direction
        );

        return [
            'filter' => $filter,
            'search' => $search,
            'is_booked' => array_key_exists('is_booked', $filters) ? (bool) $filters['is_booked'] : null,
            'processed_from' => $processedFrom,
            'processed_to' => $processedTo,
            'sender_code' => $senderCode,
            'destination_country' => $destinationCountry,
            'sort_column' => $sortColumn,
            'sort_direction' => $sortDirection,
        ];
    }

    /**
     * @return array{0:?string,1:string}
     */
    private function resolveSort(?string $sortKey, ?string $direction): array
    {
        $map = [
            'processed_at' => 'processed_at',
            'order_id' => 'external_order_id',
            'kunden_id' => 'customer_number',
            'email' => 'contact_email',
            'country' => 'destination_country',
            'rechbetrag' => 'total_amount',
            'booked_at' => 'booked_at',
            'tracking_number' => 'tracking_sort',
        ];

        if ($sortKey === null || ! array_key_exists($sortKey, $map)) {
            return [null, $direction ?? 'desc'];
        }

        return [$map[$sortKey], $direction ?? 'desc'];
    }
}
