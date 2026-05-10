<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Reporting;

use App\Domain\Monitoring\Contracts\DispatchEventReportRepository;
use Illuminate\Support\Facades\DB;

final class DatabaseDispatchEventReportRepository implements DispatchEventReportRepository
{
    /**
     * @param  array<string,mixed>  $attributes
     */
    public function upsert(string $eventId, array $attributes): bool
    {
        $inserted = DB::table('reporting_dispatch_events')->insertOrIgnore([array_merge([
            'event_id' => $eventId,
        ], $attributes)]);

        DB::table('reporting_dispatch_events')
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
