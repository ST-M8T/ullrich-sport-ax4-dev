<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Application\Fulfillment\Integrations\Dhl\Services\DhlCancellationService;
use App\Http\Controllers\Api\Admin\Concerns\InteractsWithJsonApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DhlBulkCancellationController
{
    use InteractsWithJsonApiResponses;

    public function __construct(
        private readonly DhlCancellationService $cancellationService,
    ) {}

    /**
     * POST /api/admin/dhl/bulk-cancel
     *
     * Cancels DHL shipments for multiple orders in a single request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $cancelledBy = $request->user()?->email ?? 'system';
        $reason = $validated['reason'] ?? 'Bulk cancellation';
        $orderIds = (array) $validated['order_ids'];

        $succeeded = 0;
        $failed = 0;
        $results = [];

        foreach ($orderIds as $orderId) {
            $result = $this->cancellationService->cancel((int) $orderId, $reason, $cancelledBy);

            if ($result->success) {
                $succeeded++;
            } else {
                $failed++;
            }

            $results[] = [
                'type' => 'dhl-cancellation-result',
                'id' => (string) $orderId,
                'attributes' => [
                    'order_id' => (int) $orderId,
                    'success' => $result->success,
                    'cancelled_at' => $result->cancelledAt,
                    'confirmation_number' => $result->dhlConfirmationNumber,
                    'error' => $result->error,
                ],
            ];
        }

        return $this->jsonApiResponse([
            'data' => [
                'type' => 'dhl-bulk-cancellation',
                'id' => null,
                'attributes' => [
                    'total' => count($orderIds),
                    'succeeded' => $succeeded,
                    'failed' => $failed,
                ],
            ],
            'results' => $results,
        ]);
    }
}