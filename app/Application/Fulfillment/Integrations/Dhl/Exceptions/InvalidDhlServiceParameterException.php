<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Exceptions;

/**
 * Thrown when a DhlServiceOption parameter set fails validation against the
 * service's JSON-Schema (PROJ-1 JsonSchema VO).
 */
final class InvalidDhlServiceParameterException extends DhlAdditionalServiceMappingException
{
    public function __construct(
        public readonly string $serviceCode,
        public readonly string $parameterPath,
        public readonly string $schemaViolation,
    ) {
        parent::__construct(sprintf(
            'Ungültiger Parameter für DHL Service "%s" an Pfad "%s": %s.',
            $serviceCode,
            $parameterPath,
            $schemaViolation,
        ));
    }
}
