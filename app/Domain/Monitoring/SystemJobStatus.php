<?php

declare(strict_types=1);

namespace App\Domain\Monitoring;

/**
 * Lebenszyklus eines System-Jobs (Monitoring).
 *
 * Werte korrespondieren mit den Status-Strings, die der
 * Monitoring-Bereich in der Persistenz und in CSV-Exporten verwendet.
 *
 * "Queued" und "Succeeded" werden im UI als Filter-Optionen angeboten,
 * werden aber nicht aktiv vom Lifecycle-Service gesetzt; sie bleiben
 * als reservierte Werte erhalten, damit Filterabfragen weiterhin
 * funktionieren.
 */
enum SystemJobStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public static function fromString(string $value): self
    {
        return self::from(strtolower(trim($value)));
    }

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom(strtolower(trim($value)));
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Queued => 'Queued',
            self::Running => 'Running',
            self::Succeeded => 'Succeeded',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }
}
