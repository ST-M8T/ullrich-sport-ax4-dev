<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;
use Stringable;

/**
 * Type-safe wrapper around an audit-actor identifier.
 *
 * Allowed shapes:
 * - "system:<name>"  (e.g. "system:dhl-sync", "system:migration")
 * - "user:<id>"      (e.g. "user:42")
 *
 * The domain layer NEVER pulls `auth()->user()` itself; the calling
 * presentation/application layer must construct the actor explicitly. This
 * keeps Engineering Handbook §19/§20 (auth provider stays in infrastructure)
 * intact.
 */
final readonly class AuditActor implements Stringable
{
    private const MAX_LENGTH = 128;

    public function __construct(public string $value)
    {
        if ($value === '') {
            throw DhlValueObjectException::invalid('auditActor', 'must not be empty', $value);
        }
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw DhlValueObjectException::invalid('auditActor', 'max length 128', $value);
        }
        if (! str_starts_with($value, 'system:') && ! str_starts_with($value, 'user:')) {
            throw DhlValueObjectException::invalid(
                field: 'auditActor',
                rule: 'must start with "system:" or "user:"',
                rejectedValue: $value,
            );
        }
        $suffix = substr($value, strpos($value, ':') + 1);
        if ($suffix === '') {
            throw DhlValueObjectException::invalid('auditActor', 'identifier suffix must not be empty', $value);
        }
    }

    public static function system(string $name): self
    {
        return new self('system:' . $name);
    }

    public static function user(int|string $id): self
    {
        return new self('user:' . $id);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
