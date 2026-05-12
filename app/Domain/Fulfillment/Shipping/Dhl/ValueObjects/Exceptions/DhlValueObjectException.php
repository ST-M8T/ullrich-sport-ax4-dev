<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions;

use DomainException;

/**
 * Domain exception for DHL Freight value-object invariant violations.
 *
 * Carries the offending field name, the rejected value (stringified for log
 * safety) and the violated rule so the caller / mapper can produce a precise
 * diagnostic without re-parsing the message.
 */
final class DhlValueObjectException extends DomainException
{
    public function __construct(
        string $message,
        public readonly string $field,
        public readonly string $rule,
        public readonly ?string $rejectedValue = null,
    ) {
        parent::__construct($message);
    }

    public static function invalid(string $field, string $rule, ?string $rejectedValue = null): self
    {
        $rendered = $rejectedValue ?? '<null>';
        $message = sprintf('DHL value object: field "%s" violates rule "%s" (got "%s").', $field, $rule, $rendered);

        return new self($message, $field, $rule, $rejectedValue);
    }
}
