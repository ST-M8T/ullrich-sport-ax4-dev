<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Masterdata\Exceptions;

/**
 * Thrown when a Masterdata aggregate is referenced by identifier but does not
 * exist. Subclasses identify the specific aggregate (Freight Profile,
 * Variation Profile, Sender Profile, Sender Rule, Packaging Profile,
 * Assembly Option).
 *
 * Presentation maps this family to HTTP 404 in {@see bootstrap/app.php}.
 */
abstract class MasterdataNotFoundException extends MasterdataException
{
    public function __construct(
        public readonly string $aggregate,
        public readonly int $identifier,
    ) {
        parent::__construct(sprintf('%s "%d" not found.', $aggregate, $identifier));
    }
}
