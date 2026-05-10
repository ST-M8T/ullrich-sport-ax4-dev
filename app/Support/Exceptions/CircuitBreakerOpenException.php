<?php

namespace App\Support\Exceptions;

use RuntimeException;

final class CircuitBreakerOpenException extends RuntimeException
{
    public function __construct(
        private readonly string $service,
        private readonly int $retryAfterTimestamp
    ) {
        parent::__construct(sprintf(
            'Circuit breaker is open for service "%s". Retry after %d seconds.',
            $service,
            max(0, $retryAfterTimestamp - time())
        ));
    }

    public function service(): string
    {
        return $this->service;
    }

    public function retryAfter(): int
    {
        return $this->retryAfterTimestamp;
    }
}
