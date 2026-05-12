<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\DTOs;

use App\Application\Fulfillment\Integrations\Dhl\DTOs\Concerns\HasDhlErrorParsing;

final class DhlPriceQuoteResponseDto
{
    use HasDhlErrorParsing;

    /**
     * @return array<string,mixed>
     */
    protected function rawResponseArray(): array
    {
        return $this->response;
    }

    /**
     * @param  array<string,mixed>  $response
     */
    public function __construct(
        private readonly array $response,
    ) {
        // Raw API response is retained for diagnostics.
    }

    public function totalPrice(): ?float
    {
        $price = $this->response['totalPrice'] ?? $this->response['price'] ?? $this->response['amount'] ?? null;
        if ($price === null) {
            return null;
        }

        return is_numeric($price) ? (float) $price : null;
    }

    public function currency(): string
    {
        // Fallback `'EUR'` ist immer gesetzt — non-nullable Rückgabetyp.
        return $this->response['currency'] ?? $this->response['currencyCode'] ?? 'EUR';
    }

    /**
     * @return array<string,mixed>
     */
    public function breakdown(): array
    {
        return $this->response['breakdown'] ?? $this->response['priceBreakdown'] ?? [];
    }

    /**
     * @return array<string,mixed>
     */
    public function rawResponse(): array
    {
        return $this->response;
    }

    public function isSuccess(): bool
    {
        return $this->totalPrice() !== null;
    }

}
