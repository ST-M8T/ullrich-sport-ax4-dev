<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Application\Fulfillment\Integrations\Dhl\Services\DhlCancellationService;
use App\Http\Controllers\Api\Admin\Concerns\InteractsWithJsonApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DhlCancellationController
{
    use InteractsWithJsonApiResponses;

    public function __construct(
        private readonly DhlCancellationService $cancellationService,
    ) {}

    /**
     * DELETE /api/admin/dhl/shipment/{shipmentOrderId}
     *
     * Cancels a DHL shipment for an existing shipment order.
     */
    public function destroy(Request $request, int $shipmentOrderId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $reason = $validated['reason'] ?? 'No reason provided';
        $cancelledBy = $request->user()?->email ?? 'system';

        $result = $this->cancellationService->cancel($shipmentOrderId, $reason, $cancelledBy);

        if ($result->success === false) {
            return $this->jsonApiError(422, 'Unprocessable Entity', $result->error ?? 'Cancellation failed');
        }

        return $this->jsonApiResponse([
            'data' => [
                'type' => 'dhl-cancellation',
                'id' => (string) $shipmentOrderId,
                'attributes' => [
                    'shipment_order_id' => $shipmentOrderId,
                    'cancelled_at' => $result->cancelledAt,
                    'confirmation_number' => $result->dhlConfirmationNumber,
                    'status' => 'cancelled',
                ],
            ],
        ]);
    }
}