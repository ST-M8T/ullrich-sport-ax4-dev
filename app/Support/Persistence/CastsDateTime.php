<?php

declare(strict_types=1);

namespace App\Support\Persistence;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Hilft Eloquent-Repositories beim typsicheren Mapping beliebiger Datums-Werte
 * (Carbon, DateTime, DateTimeImmutable, ISO-8601-Strings, null) auf
 * `?DateTimeImmutable` für Domain-Entities.
 *
 * Hintergrund: Eloquent liefert Datumsfelder typischerweise als `Carbon`
 * (mutable). Domain-Entities erwarten `DateTimeImmutable`. Statt diese
 * Konvertierung in jedem Repository zu duplizieren, kapselt der Trait die
 * Logik an einer Stelle (DRY, §61 Engineering-Handbuch).
 *
 * Beispiel:
 * ```php
 * use App\Support\Persistence\CastsDateTime;
 *
 * final class EloquentXyzRepository
 * {
 *     use CastsDateTime;
 *
 *     private function mapModel(XyzModel $model): Xyz
 *     {
 *         return Xyz::hydrate(
 *             // ...
 *             $this->toImmutable($model->scheduled_at),
 *             $this->toImmutable($model->created_at) ?? new DateTimeImmutable(),
 *         );
 *     }
 * }
 * ```
 */
trait CastsDateTime
{
    private function toImmutable(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return new DateTimeImmutable((string) $value);
    }
}
