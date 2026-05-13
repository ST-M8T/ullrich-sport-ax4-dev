<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Exceptions;

use RuntimeException;

/**
 * Base exception for every failure that can occur while translating a
 * DhlServiceOptionCollection into a DHL API payload via
 * DhlAdditionalServiceMapper.
 *
 * Bulk callers catch this single base class to collect per-shipment failures
 * without having to enumerate every concrete subclass. Concrete subclasses
 * remain distinguishable via `instanceof` for the presentation layer.
 *
 * Engineering-Handbuch §16: stays a domain/application exception — no HTTP
 * status code is carried; mapping to HTTP happens in the Presentation layer.
 */
class DhlAdditionalServiceMappingException extends RuntimeException
{
}
