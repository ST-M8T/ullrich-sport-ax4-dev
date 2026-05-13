<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Exceptions;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\RoutingContext;

/**
 * Thrown when the catalog marks a service as `forbidden` for the given
 * product / routing combination.
 */
final class ForbiddenDhlServiceException extends DhlAdditionalServiceMappingException
{
    public function __construct(
        public readonly string $serviceCode,
        public readonly DhlProductCode $productCode,
        public readonly RoutingContext $routing,
    ) {
        parent::__construct(sprintf(
            'DHL Service "%s" ist für Produkt "%s" und Routing %s verboten.',
            $serviceCode,
            $productCode->value,
            sprintf(
                '%s→%s payer=%s',
                $routing->fromCountry() ?? '*',
                $routing->toCountry() ?? '*',
                $routing->payerCode()?->value ?? '*',
            ),
        ));
    }
}
