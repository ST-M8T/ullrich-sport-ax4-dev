<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Orders\Queries;

use App\Http\Requests\Fulfillment\ShipmentOrderIndexRequest;
use Carbon\CarbonImmutable;

/**
 * Transforms a validated {@see ShipmentOrderIndexRequest} into a typed
 * {@see ShipmentOrderIndexCriteria} DTO.
 *
 * Lives in the Application layer because parsing/normalising filter inputs
 * is use-case orchestration, not Presentation concern (Handbook §7, §70).
 */
final class ShipmentOrderIndexRequestTransformer
{
    public function transform(ShipmentOrderIndexRequest $request): ShipmentOrderIndexCriteria
    {
        $validated = $request->validated();

        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = $this->resolvePerPage($validated);

        $filter = $validated['filter'] ?? null;
        $search = $validated['search'] ?? null;
        $sort = $validated['sort'] ?? 'processed_at';
        $direction = $this->resolveDirection($validated['dir'] ?? 'desc');

        $senderCode = (string) ($validated['sender_code'] ?? '');
        $destinationCountryRaw = (string) ($validated['destination_country'] ?? '');
        $destinationCountry = strtoupper($destinationCountryRaw);

        $processedFromRaw = (string) ($validated['processed_from'] ?? '');
        $processedToRaw = (string) ($validated['processed_to'] ?? '');

        $filters = [
            'filter' => $filter,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ];

        if ($senderCode !== '') {
            $filters['sender_code'] = $senderCode;
        }

        if ($destinationCountry !== '') {
            $filters['destination_country'] = $destinationCountry;
        }

        if (array_key_exists('is_booked', $validated)
            && $validated['is_booked'] !== null
            && $validated['is_booked'] !== ''
        ) {
            $filters['is_booked'] = (bool) (int) $validated['is_booked'];
        }

        $timezone = config('app.timezone');

        $processedFrom = $this->parseDate($processedFromRaw, $timezone, startOfDay: true);
        if ($processedFrom !== null) {
            $filters['processed_from'] = $processedFrom;
        }

        $processedTo = $this->parseDate($processedToRaw, $timezone, startOfDay: false);
        if ($processedTo !== null) {
            $filters['processed_to'] = $processedTo;
        }

        $viewQuery = [
            'filter' => $filter,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'sender_code' => $senderCode,
            'destination_country' => $destinationCountry,
            'processed_from' => $processedFromRaw,
            'processed_to' => $processedToRaw,
            'is_booked' => $filters['is_booked'] ?? null,
        ];

        $expandId = isset($validated['expand']) ? (int) $validated['expand'] : null;
        if ($expandId !== null && $expandId <= 0) {
            $expandId = null;
        }

        return new ShipmentOrderIndexCriteria(
            page: $page,
            perPage: $perPage,
            filters: $filters,
            viewQuery: $viewQuery,
            expandId: $expandId,
        );
    }

    /**
     * @param  array<string,mixed>  $validated
     */
    private function resolvePerPage(array $validated): int
    {
        $max = (int) config('performance.pagination.max_page_size');
        $default = (int) config('performance.pagination.default_page_size');
        $requested = (int) ($validated['per_page'] ?? $default);

        return max(1, min($max, $requested));
    }

    private function resolveDirection(mixed $raw): string
    {
        $direction = strtolower((string) $raw);

        return in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
    }

    private function parseDate(string $raw, ?string $timezone, bool $startOfDay): ?\DateTimeImmutable
    {
        if ($raw === '') {
            return null;
        }

        $parsed = CarbonImmutable::createFromFormat('Y-m-d', $raw, $timezone);
        if ($parsed === null) {
            return null;
        }

        $boundary = $startOfDay ? $parsed->startOfDay() : $parsed->endOfDay();

        return $boundary->toDateTimeImmutable();
    }
}
