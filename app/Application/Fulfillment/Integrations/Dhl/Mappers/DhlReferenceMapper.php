<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Mappers;

use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlReference;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlReferenceQualifier;

/**
 * Stateless reference mapper — derives DHL Freight references[] entries
 * from a {@see ShipmentOrder}.
 *
 * Mapping:
 *   - CNR (mandatory) — externalOrderId.
 *   - CNZ (optional)  — customerNumber, only emitted when present.
 *   - INV             — out of scope (YAGNI).
 *
 * All values are truncated to the spec-allowed 35 chars.
 */
final class DhlReferenceMapper
{
    private const MAX_VALUE = 35;

    /**
     * @return list<DhlReference>
     */
    public static function fromOrder(ShipmentOrder $order): array
    {
        $references = [];

        $external = (string) $order->externalOrderId();
        if ($external !== '') {
            $references[] = new DhlReference(
                qualifier: DhlReferenceQualifier::CNR,
                value: self::truncate($external),
            );
        }

        $customerNumber = $order->customerNumber();
        if ($customerNumber !== null) {
            $value = (string) $customerNumber;
            if ($value !== '') {
                $references[] = new DhlReference(
                    qualifier: DhlReferenceQualifier::CNZ,
                    value: self::truncate($value),
                );
            }
        }

        return $references;
    }

    private static function truncate(string $value): string
    {
        $value = trim($value);

        return mb_strlen($value) > self::MAX_VALUE
            ? mb_substr($value, 0, self::MAX_VALUE)
            : $value;
    }
}
