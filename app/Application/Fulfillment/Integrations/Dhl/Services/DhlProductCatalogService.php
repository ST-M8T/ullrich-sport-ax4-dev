<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Services;

use App\Domain\Integrations\Contracts\DhlFreightGateway;
use Throwable;

final class DhlProductCatalogService
{
    public function __construct(
        private readonly DhlFreightGateway $gateway,
    ) {}

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listProducts(array $filters = []): array
    {
        return $this->gateway->listProducts($filters);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listAdditionalServices(string $productId, array $filters = []): array
    {
        return $this->gateway->listAdditionalServices($productId, $filters);
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array{valid:bool,errors:array<int,string>}
     */
    public function validateAdditionalServices(string $productId, array $services, array $filters = []): array
    {
        try {
            $response = $this->gateway->validateAdditionalServices($productId, $services, $filters);

            return [
                'valid' => $response['valid'] ?? $response['isValid'] ?? true,
                'errors' => $response['errors'] ?? $response['validationErrors'] ?? [],
            ];
        } catch (Throwable) {
            return ['valid' => false, 'errors' => ['Validierung fehlgeschlagen']];
        }
    }
}
