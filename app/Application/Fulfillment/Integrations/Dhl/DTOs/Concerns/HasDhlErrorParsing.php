<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\DTOs\Concerns;

/**
 * Single source of truth for DHL Freight error-message extraction.
 *
 * The DHL Freight API surfaces failure descriptions under one of three keys
 * (`error`, `message`, `errorMessage`) depending on the endpoint. The consuming
 * response DTO is responsible for {@see isSuccess()} (it knows the success
 * predicate of its own payload) and exposes {@see rawResponseArray()} so this
 * trait can locate the error key without coupling to a specific shape.
 */
trait HasDhlErrorParsing
{
    /**
     * @return array<string,mixed>
     */
    abstract protected function rawResponseArray(): array;

    abstract public function isSuccess(): bool;

    public function errorMessage(): ?string
    {
        if ($this->isSuccess()) {
            return null;
        }

        $response = $this->rawResponseArray();

        $candidate = $response['error']
            ?? $response['message']
            ?? $response['errorMessage']
            ?? null;

        return is_string($candidate) ? $candidate : null;
    }
}
