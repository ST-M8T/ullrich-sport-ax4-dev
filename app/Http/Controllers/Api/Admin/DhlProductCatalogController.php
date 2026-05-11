<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Application\Fulfillment\Integrations\Dhl\Services\DhlProductCatalogService;
use App\Http\Controllers\Api\Admin\Concerns\InteractsWithJsonApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DhlProductCatalogController
{
    use InteractsWithJsonApiResponses;

    public function __construct(
        private readonly DhlProductCatalogService $catalogService,
    ) {}

    public function listProducts(): JsonResponse
    {
        try {
            $products = $this->catalogService->listProducts();

            return $this->jsonApiResponse([
                'data' => array_map(
                    fn (array $product): array => [
                        'type' => 'dhl-product',
                        'id' => $product['productId'] ?? $product['product_id'] ?? '',
                        'attributes' => [
                            'product_id' => $product['productId'] ?? $product['product_id'] ?? null,
                            'name' => $product['name'] ?? null,
                            'description' => $product['description'] ?? null,
                            'valid_until' => $product['validUntil'] ?? $product['valid_until'] ?? null,
                        ],
                    ],
                    $products
                ),
            ]);
        } catch (\Throwable $exception) {
            return $this->jsonApiError(500, 'Internal Server Error', $exception->getMessage());
        }
    }

    public function listAdditionalServices(Request $request): JsonResponse
    {
        $productId = $request->query('product_id');
        if ($productId === null || $productId === '') {
            return $this->jsonApiValidationErrors(['product_id' => ['product_id is required']]);
        }

        try {
            $services = $this->catalogService->listAdditionalServices($productId);

            return $this->jsonApiResponse([
                'data' => array_map(
                    fn (array $service): array => [
                        'type' => 'dhl-service',
                        'id' => $service['serviceCode'] ?? $service['service_code'] ?? '',
                        'attributes' => [
                            'service_code' => $service['serviceCode'] ?? $service['service_code'] ?? null,
                            'name' => $service['name'] ?? null,
                            'description' => $service['description'] ?? null,
                        ],
                    ],
                    $services
                ),
            ]);
        } catch (\Throwable $exception) {
            return $this->jsonApiError(500, 'Internal Server Error', $exception->getMessage());
        }
    }

    public function validateServices(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'string', 'max:64'],
            'services' => ['required', 'array'],
            'services.*' => ['string', 'max:64'],
        ]);

        try {
            $result = $this->catalogService->validateAdditionalServices(
                $validated['product_id'],
                $validated['services']
            );

            return $this->jsonApiResponse([
                'data' => [
                    'type' => 'dhl-service-validation',
                    'id' => null,
                    'attributes' => [
                        'valid' => $result['valid'] ?? false,
                        'errors' => $result['errors'] ?? [],
                        'product_id' => $validated['product_id'],
                    ],
                ],
            ]);
        } catch (\Throwable $exception) {
            return $this->jsonApiError(500, 'Internal Server Error', $exception->getMessage());
        }
    }
}
