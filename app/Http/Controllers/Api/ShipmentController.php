<?php

namespace App\Http\Controllers\Api;

use App\Application\Fulfillment\Shipments\Queries\GetShipmentDetail;
use Illuminate\Http\JsonResponse;

final class ShipmentController
{
    public function __construct(private readonly GetShipmentDetail $query) {}

    public function show(string $trackingNumber): JsonResponse
    {
        $resource = ($this->query)($trackingNumber);

        if (! $resource) {
            return response()->json(['message' => 'Shipment not found'], 404);
        }

        return response()->json($resource->toArray());
    }
}
