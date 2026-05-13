<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Masterdata\Exceptions;

use DomainException;

/**
 * Base domain exception for the Fulfillment Masterdata bounded sub-context.
 *
 * Every masterdata-specific exception extends this so callers can catch the
 * whole family without depending on framework exceptions. Carries no HTTP
 * status code and no framework reference — pure domain
 * (Engineering Handbook §16 Fehlerregel).
 */
abstract class MasterdataException extends DomainException
{
}
