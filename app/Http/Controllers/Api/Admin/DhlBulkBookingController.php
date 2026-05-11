<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Application\Fulfillment\Integrations\Dhl\Services\DhlBulkBookingService;
use App\Http\Controllers\Api\Admin\Concerns\InteractsWithJsonApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['required', 'integer', 'min:1'],
            'product_id' => ['nullable', 'string', 'max:64'],
            'additional_services' => ['nullable', 'array'],
            'additional_services.*' => ['string', 'max:64'],
            'pickup_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        try {
            $result = ($this->bulkBookingService)->bookBulk(
                (array) $validated['order_ids'],
                (string) ($validated['product_id'] ?? ''),
                (array) ($validated['additional_services'] ?? []),
                isset($validated['pickup_date']) ? (string) $validated['pickup_date'] : null,
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