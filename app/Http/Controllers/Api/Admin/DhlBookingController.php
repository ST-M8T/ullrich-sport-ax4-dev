<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlBookingOptions;
use App\Application\Fulfillment\Integrations\Dhl\Services\DhlShipmentBookingService;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Http\Controllers\Api\Admin\Concerns\InteractsWithJsonApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DhlBookingController
{
    use InteractsWithJsonApiResponses;

    public function __construct(
        private readonly DhlShipmentBookingService $bookingService,
        private readonly ShipmentOrderRepository $orderRepository,
    ) {}

    /**
     * POST /api/admin/dhl/booking
     *
     * Creates a DHL shipment booking for an existing shipment order.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'min:1'],
            'product_id' => ['nullable', 'string', 'max:64'],
            'additional_services' => ['nullable', 'array'],
            'additional_services.*' => ['string', 'max:64'],
            'pickup_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        try {
            $orderId = Identifier::fromInt((int) $validated['order_id']);
            $options = DhlBookingOptions::fromArray($validated);

            $result = ($this->bookingService)->bookShipment($orderId, $options);

            if ($result->success === false) {
                return $this->jsonApiError(422, 'Unprocessable Entity', $result->error ?? 'Booking failed');
            }

            return $this->jsonApiResponse([
                'data' => [
                    'type' => 'dhl-booking',
                    'id' => $result->shipmentId,
                    'attributes' => [
                        'shipment_id' => $result->shipmentId,
                        'tracking_numbers' => $result->trackingNumbers,
                        'booked_at' => (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
                        'status' => 'booked',
                    ],
                ],
            ], 201);
        } catch (\Throwable $exception) {
            return $this->jsonApiError(500, 'Internal Server Error', $exception->getMessage());
        }
    }

    /**
     * GET /api/admin/dhl/booking/{shipmentOrderId}
     *
     * Returns the booking status and details for a given shipment order.
     */
    public function show(int $shipmentOrderId): JsonResponse
    {
        try {
            $orderId = Identifier::fromInt($shipmentOrderId);
            $order = $this->orderRepository->getById($orderId);

            if ($order === null) {
                return $this->jsonApiError(404, 'Not Found', 'Shipment order not found');
            }

            $isBooked = $order->isBooked();

            return $this->jsonApiResponse([
                'data' => [
                    'type' => 'dhl-booking',
                    'id' => (string) $shipmentOrderId,
                    'attributes' => [
                        'shipment_id' => $order->dhlShipmentId(),
                        'tracking_numbers' => $order->trackingNumbers(),
                        'booked_at' => $order->bookedAt()?->format(\DateTimeInterface::ATOM),
                        'status' => $isBooked ? 'booked' : 'not_booked',
                        'product_id' => $order->dhlProductId(),
                        'booking_error' => $order->dhlBookingError(),
                        'label_url' => $order->dhlLabelUrl(),
                        'pickup_reference' => $order->dhlPickupReference(),
                    ],
                ],
            ]);
        } catch (\Throwable $exception) {
            return $this->jsonApiError(500, 'Internal Server Error', $exception->getMessage());
        }
    }
}