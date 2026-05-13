<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Application\Fulfillment\Integrations\Dhl\Catalog\Queries\ComputeAllowedDhlServicesIntersection;
use App\Application\Fulfillment\Integrations\Dhl\Catalog\Queries\GetAllowedDhlServices;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\CountryCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPayerCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use App\Http\Controllers\Api\Admin\Concerns\InteractsWithJsonApiResponses;
use App\Http\Requests\Api\Admin\AllowedDhlServicesIntersectionRequest;
use App\Http\Requests\Api\Admin\AllowedDhlServicesRequest;
use Illuminate\Http\JsonResponse;

/**
 * Read-only API endpoint serving the dynamic DHL service catalog for the
 * booking UI (PROJ-5).
 *
 * Engineering-Handbuch §7 (thin Presentation layer — controller only validates,
 * delegates to the Application query, formats response), §22 (consistent API
 * contract), §29 (caching delegated to Application service).
 */
final class AllowedDhlServicesController
{
    use InteractsWithJsonApiResponses;

    public function __construct(
        private readonly GetAllowedDhlServices $allowedServices,
        private readonly ComputeAllowedDhlServicesIntersection $intersection,
    ) {}

    public function show(AllowedDhlServicesRequest $request): JsonResponse
    {
        $payload = $this->allowedServices->execute(
            new DhlProductCode($request->productCode()),
            new CountryCode($request->fromCountry()),
            new CountryCode($request->toCountry()),
            DhlPayerCode::fromString($request->payerCode()),
        );

        return $this->jsonApiResponse($payload);
    }

    public function intersection(AllowedDhlServicesIntersectionRequest $request): JsonResponse
    {
        $routings = [];
        foreach ($request->routings() as $r) {
            $routings[] = [
                'product' => new DhlProductCode($r['product_code']),
                'from' => new CountryCode($r['from_country']),
                'to' => new CountryCode($r['to_country']),
                'payer' => DhlPayerCode::fromString($r['payer_code']),
            ];
        }

        $payload = $this->intersection->execute($routings);

        return $this->jsonApiResponse($payload);
    }
}
