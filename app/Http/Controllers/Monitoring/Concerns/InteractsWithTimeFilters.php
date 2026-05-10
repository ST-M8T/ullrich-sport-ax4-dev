<?php

namespace App\Http\Controllers\Monitoring\Concerns;

use DateInterval;
use DateTimeImmutable;

trait InteractsWithTimeFilters
{
    /**
     * @return array<string,string>
     */
    private function timeRangeOptions(): array
    {
        return [
            '1h' => 'Letzte Stunde',
            '12h' => 'Letzte 12 Stunden',
            '24h' => 'Letzte 24 Stunden',
            '7d' => 'Letzte 7 Tage',
            '30d' => 'Letzte 30 Tage',
        ];
    }

    /**
     * @return array{0: ?DateTimeImmutable, 1: ?DateTimeImmutable}
     */
    private function resolveTimeRange(?string $range): array
    {
        if (! $range) {
            return [null, null];
        }

        $now = new DateTimeImmutable;

        return match ($range) {
            '1h' => [$now->sub(new DateInterval('PT1H')), $now],
            '12h' => [$now->sub(new DateInterval('PT12H')), $now],
            '24h' => [$now->sub(new DateInterval('P1D')), $now],
            '7d' => [$now->sub(new DateInterval('P7D')), $now],
            '30d' => [$now->sub(new DateInterval('P30D')), $now],
            default => [null, null],
        };
    }

    private function formatDateForInput(?DateTimeImmutable $value): string
    {
        return $value ? $value->format('Y-m-d\TH:i') : '';
    }

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        if (! $value) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
