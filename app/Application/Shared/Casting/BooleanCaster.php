<?php

declare(strict_types=1);

namespace App\Application\Shared\Casting;

/**
 * Stateless helper for normalising loose truthy values (form inputs,
 * stored "0"/"1" strings, JSON booleans) into a strict PHP boolean.
 */
final class BooleanCaster
{
    public static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }
}
