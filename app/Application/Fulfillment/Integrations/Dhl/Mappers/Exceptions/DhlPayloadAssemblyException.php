<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Mappers\Exceptions;

use RuntimeException;

/**
 * Thrown when the DHL Freight payload assembler cannot construct a spec-conforming
 * payload because of missing or invalid input data (e.g. missing receiver address,
 * empty package list, missing payer code).
 *
 * Distinct from {@see \App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException}
 * which signals VO-level invariant violations. Application-layer assembler errors
 * surface here so callers can map them to a uniform booking-failure response.
 */
final class DhlPayloadAssemblyException extends RuntimeException
{
    public static function missing(string $field, string $context = ''): self
    {
        $msg = sprintf('DHL payload assembly: missing required field "%s"', $field);
        if ($context !== '') {
            $msg .= sprintf(' (%s)', $context);
        }

        return new self($msg);
    }

    public static function invalid(string $field, string $reason): self
    {
        return new self(sprintf('DHL payload assembly: field "%s" invalid — %s', $field, $reason));
    }
}
