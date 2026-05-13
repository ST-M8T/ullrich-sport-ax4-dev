<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions;

/**
 * Thrown when the catalog's JsonSchema VO encounters an unsupported construct
 * (e.g. `oneOf`, `anyOf`, `allOf`, `$ref`, `if/then/else`).
 *
 * The catalog deliberately whitelists a small subset of JSON Schema Draft
 * 2020-12 to keep the surface tight (KISS + security §19). Any drift from DHL
 * that introduces a new construct surfaces as this exception, NOT as a silent
 * mis-validation.
 */
final class UnsupportedJsonSchemaFeatureException extends DhlCatalogException
{
    public function __construct(public readonly string $unsupportedKey)
    {
        parent::__construct(sprintf(
            'Unsupported JSON Schema feature in DHL catalog: "%s".',
            $unsupportedKey,
        ));
    }
}
