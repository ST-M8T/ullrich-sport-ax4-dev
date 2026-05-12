<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Application\Fulfillment\Integrations\Dhl\Services\DhlBulkBookingService;
use App\Http\Controllers\Api\Admin\Concerns\InteractsWithJsonApiResponses;
use App\Http\Requests\Fulfillment\DhlBulkBookingRequest;
use Illuminate\Http\JsonResponse;

final class DhlBulkBookingController
{
    use InteractsWithJsonApiResponses;

    public function __construct(
        private readonly DhlBulkBookingService $bulkBookingService,
    ) {}

    /**
     * POST /api/admin/dhl/bulk-book
     *
     * Books DHL shipments for multiple orders in a single request.
     * When more than 10 orders are provided, processing is delegated to a queue job.
     *
     * @param DhlBulkBookingRequest $request
     * @return JsonResponse
     */
    public function store(DhlBulkBookingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            // Bulk-Service nimmt aktuell den legacy product_id-String entgegen.
            // Wir bevorzugen den validierten product_code (3 Zeichen, uppercase),
            // fallen fuer Backwards-Compat auf product_id zurueck.
            $productString = isset($validated['product_code'])
                ? (string) $validated['product_code']
                : (string) ($validated['product_id'] ?? '');

            $result = ($this->bulkBookingService)->bookBulk(
                (array) $validated['order_ids'],
                $productString,
                (array) ($validated['additional_services'] ?? []),
                isset($validated['pickup_date']) ? (string) $validated['pickup_date'] : null,
                isset($validated['payer_code']) ? (string) $validated['payer_code'] : null,
                isset($validated['default_package_type']) ? (string) $validated['default_package_type'] : null,
            );

            if ($result['queued']) {
                return $this->jsonApiResponse([
                    'data' => [
                        'type' => 'dhl-bulk-booking',
                        'id' => null,
                        'attributes' => [
                            'total' => $result['total'],
                            'succeeded' => $result['succeeded'],
                            'failed' => $result['failed'],
                            'queued' => true,
                            'message' => $result['message'] ?? 'Bulk booking queued for processing.',
                        ],
                    ],
                ], 202);
            }

            $results = array_map(
                fn (array $r): array => [
                    'type' => 'dhl-bulk-booking-result',
                    'id' => (string) $r['orderId'],
                    'attributes' => [
                        'order_id' => $r['orderId'],
                        'success' => $r['success'],
                        'shipment_id' => $r['shipmentId'] ?? null,
                        'tracking_numbers' => $r['trackingNumbers'] ?? [],
                        'error' => $r['error'] ?? null,
                    ],
                ],
                $result['results']
            );

            return $this->jsonApiResponse([
                'data' => [
                    'type' => 'dhl-bulk-booking',
                    'id' => null,
                    'attributes' => [
                        'total' => $result['total'],
                        'succeeded' => $result['succeeded'],
                        'failed' => $result['failed'],
                        'queued' => false,
                    ],
                ],
                'results' => $results,
            ], 200);
        } catch (\Throwable $exception) {
            return $this->jsonApiError(500, 'Internal Server Error', $exception->getMessage());
        }
    }
}