<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Exceptions;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\RoutingContext;

/**
 * Thrown when the requested service code is not present at all in the DHL
 * additional-service catalog (neither globally nor for the given routing).
 */
final class UnknownDhlServiceException extends DhlAdditionalServiceMappingException
{
    public function __construct(
        public readonly string $serviceCode,
        public readonly DhlProductCode $productCode,
        public readonly RoutingContext $routing,
    ) {
        parent::__construct(sprintf(
            'Unbekannter DHL Service "%s" für Produkt "%s" und Routing %s.',
            $serviceCode,
            $productCode->value,
            self::describeRouting($routing),
        ));
    }

    private static function describeRouting(RoutingContext $routing): string
    {
        return sprintf(
            '%s→%s payer=%s',
            $routing->fromCountry() ?? '*',
            $routing->toCountry() ?? '*',
            $routing->payerCode()?->value ?? '*',
        );
    }
}
