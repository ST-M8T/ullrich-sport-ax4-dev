<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Exceptions;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\RoutingContext;

/**
 * Thrown when one or more services marked `required` in the catalog for the
 * given product / routing combination are missing from the option list.
 */
final class MissingRequiredDhlServiceException extends DhlAdditionalServiceMappingException
{
    /**
     * @param  list<string>  $missingCodes
     */
    public function __construct(
        public readonly array $missingCodes,
        public readonly DhlProductCode $productCode,
        public readonly RoutingContext $routing,
    ) {
        parent::__construct(sprintf(
            'Pflicht-Services fehlen für Produkt "%s" und Routing %s: %s.',
            $productCode->value,
            sprintf(
                '%s→%s payer=%s',
                $routing->fromCountry() ?? '*',
                $routing->toCountry() ?? '*',
                $routing->payerCode()?->value ?? '*',
            ),
            implode(', ', $missingCodes),
        ));
    }
}
