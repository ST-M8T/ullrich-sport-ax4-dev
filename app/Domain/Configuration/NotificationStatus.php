<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

/**
 * Lebenszyklus einer Notification-Message.
 *
 * Werte korrespondieren mit den Status-Strings, die in der
 * Persistenz (notifications_queue.status) gespeichert werden.
 */
enum NotificationStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
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
        return self::from(strtolower(trim($value)));
    }

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom(strtolower(trim($value)));
    }
}
