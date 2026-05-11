<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Integrations\Contracts\DhlFreightGateway;
use App\Http\Controllers\Api\Admin\Concerns\InteractsWithJsonApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DhlTimetableController
{
    use InteractsWithJsonApiResponses;

    public function __construct(
        private readonly DhlFreightGateway $gateway,
    ) {}

    /**
     * GET /api/admin/dhl/timetable
     *
     * Returns DHL freight timetable data for given origin, destination and pickup date.
     */
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'origin_postal_code' => ['required', 'string', 'max:16'],
            'destination_postal_code' => ['required', 'string', 'max:16'],
            'pickup_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        try {
            $response = $this->gateway->getTimetable([
                'originPostalCode' => $validated['origin_postal_code'],
                'destinationPostalCode' => $validated['destination_postal_code'],
                'pickupDate' => $validated['pickup_date'],
            ]);

            return $this->jsonApiResponse([
                'data' => [
                    'type' => 'dhl-timetable',
                    'id' => null,
                    'attributes' => $response,
                ],
            ]);
        } catch (\Throwable $exception) {
            return $this->jsonApiError(500, 'Internal Server Error', $exception->getMessage());
        }
    }
}