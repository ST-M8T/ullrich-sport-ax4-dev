<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Reporting;

use App\Domain\Monitoring\Contracts\OrderEventReportRepository;
use Illuminate\Support\Facades\DB;

final class DatabaseOrderEventReportRepository implements OrderEventReportRepository
{
    /**
     * @param  array<string,mixed>  $attributes
     */
    public function upsert(string $eventId, array $attributes): bool
    {
        $inserted = DB::table('reporting_order_events')->insertOrIgnore([array_merge([
            'event_id' => $eventId,
        ], $attributes)]);

        DB::table('reporting_order_events')
            ->where('event_id', $eventId)
            ->update($this->updatableAttributes($attributes));

        return $inserted > 0;
    }

    /**
     * @param  array<string,mixed>  $attributes
     * @return array<string,mixed>
     */
    private function updatableAttributes(array $attributes): array
    {
        $updates = $attributes;
        unset($updates['created_at']);

        return $updates;
    }
}
