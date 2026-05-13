<?php

declare(strict_types=1);

namespace App\Domain\Tracking;

/**
 * Lebenszyklus eines Tracking-Jobs.
 *
 * Werte korrespondieren mit den Status-Strings in der
 * Persistenz (tracking_jobs.status). "Pending" ist ein Legacy-/Quell-Wert,
 * der beim Hydrieren auf Scheduled normalisiert wird — siehe
 * {@see TrackingJob::sanitizeStatus()}.
 */
enum TrackingJobStatus: string
{
    case Scheduled = 'scheduled';
    case Reserved = 'reserved';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));

        // Legacy-Wert "pending" wird auf Scheduled abgebildet.
        if ($normalized === 'pending') {
            return self::Scheduled;
        }

        return self::from($normalized);
    }

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim($value));

        if ($normalized === 'pending') {
            return self::Scheduled;
        }

        return self::tryFrom($normalized);
    }
}
