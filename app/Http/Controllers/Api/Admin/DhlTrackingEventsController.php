<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlEventCodeLabel;
use App\Application\Fulfillment\Shipments\Queries\GetShipmentDetail;
use App\Application\Fulfillment\Shipments\Resources\ShipmentEventResource;
use Illuminate\Http\JsonResponse;

final class DhlTrackingEventsController
{
    public function __construct(
        private readonly GetShipmentDetail $getShipmentDetail,
    ) {}

    /**
     * GET /api/admin/dhl/tracking/{trackingNumber}/events
     *
     * Returns tracking events with German labels for a given tracking number.
     */
    public function show(string $trackingNumber): JsonResponse
    {
        $resource = ($this->getShipmentDetail)($trackingNumber);

        if (! $resource) {
            return response()->json([
                'success' => false,
                'error' => 'Tracking number not found',
            ], 404);
        }

        $data = $resource->toArray();
        $currentStatus = $data['status_code'] ?? null;

        $eventsWithLabels = array_map(
            static fn (array $event): array => [
                'event_code' => $event['event_code'],
                'label' => DhlEventCodeLabel::label($event['event_code'] ?? ''),
                'status' => $event['status'],
                'description' => $event['description'],
                'facility' => $event['facility'],
                'city' => $event['city'],
                'country' => $event['country'],
                'occurred_at' => $event['occurred_at'],
                'created_at' => $event['created_at'],
            ],
            $data['events'] ?? []
        );

        // Reverse to show oldest first (chronological)
        $eventsWithLabels = array_reverse($eventsWithLabels);

        return response()->json([
            'success' => true,
            'tracking_number' => $trackingNumber,
            'current_status' => [
                'code' => $currentStatus,
                'label' => DhlEventCodeLabel::label($currentStatus ?? ''),
            ],
            'is_delivered' => $data['is_delivered'] ?? false,
            'last_event_at' => $data['last_event_at'] ?? null,
            'delivered_at' => $data['delivered_at'] ?? null,
            'events' => $eventsWithLabels,
        ]);
    }
}