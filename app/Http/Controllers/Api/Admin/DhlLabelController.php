<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Application\Fulfillment\Integrations\Dhl\Services\DhlLabelService;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Http\Controllers\Api\Admin\Concerns\InteractsWithJsonApiResponses;
use Illuminate\Http\JsonResponse;

final class DhlLabelController
{
    use InteractsWithJsonApiResponses;

    public function __construct(
        private readonly DhlLabelService $labelService,
        private readonly ShipmentOrderRepository $orderRepository,
    ) {}

    /**
     * GET /api/admin/dhl/label/{shipmentOrderId}
     *
     * Returns the label URL and PDF base64 for a given shipment order.
     */
    public function show(int $shipmentOrderId): JsonResponse
    {
        try {
            $orderId = Identifier::fromInt($shipmentOrderId);
            $order = $this->orderRepository->getById($orderId);

            if ($order === null) {
                return $this->jsonApiError(404, 'Not Found', 'Shipment order not found');
            }

            if ($order->dhlShipmentId() === null) {
                return $this->jsonApiError(422, 'Unprocessable Entity', 'Shipment has not been booked yet. Book the shipment first.');
            }

            $result = ($this->labelService)->generateLabel($orderId);

            if ($result->success === false) {
                return $this->jsonApiError(422, 'Unprocessable Entity', $result->error ?? 'Label generation failed');
            }

            return $this->jsonApiResponse([
                'data' => [
                    'type' => 'dhl-label',
                    'id' => (string) $shipmentOrderId,
                    'attributes' => [
                        'label_url' => $result->labelUrl,
                        'label_pdf_base64' => $result->labelPdfBase64,
                    ],
                ],
            ]);
        } catch (\Throwable $exception) {
            return $this->jsonApiError(500, 'Internal Server Error', $exception->getMessage());
        }
    }
}