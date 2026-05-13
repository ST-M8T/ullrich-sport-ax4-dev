<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Exceptions;

/**
 * Thrown by DhlAdditionalServiceMapper when the catalog is empty AND
 * `dhl-catalog.strict_validation` is `true`. Signals an operational error
 * (sync never ran or wiped the catalog) rather than a fachlicher Fehler.
 */
final class DhlCatalogNotPopulatedException extends DhlAdditionalServiceMappingException
{
    public function __construct()
    {
        parent::__construct(
            'DHL Katalog ist leer — strict_validation aktiv. Bitte Katalog-Sync laufen lassen.',
        );
    }
}
