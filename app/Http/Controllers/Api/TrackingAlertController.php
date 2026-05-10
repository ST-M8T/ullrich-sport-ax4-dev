<?php

namespace App\Http\Controllers\Api;

use App\Application\Tracking\Queries\ListTrackingAlerts;
use App\Application\Tracking\Resources\TrackingAlertResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TrackingAlertController
{
    public function __construct(private readonly ListTrackingAlerts $listAlerts) {}

    public function index(Request $request): JsonResponse
    {
        $filters = array_filter([
            'alert_type' => $request->query('alert_type'),
            'severity' => $request->query('severity'),
            'channel' => $request->query('channel'),
            'is_acknowledged' => $request->has('is_acknowledged') ? (bool) $request->boolean('is_acknowledged') : null,
        ], fn ($value) => $value !== null && $value !== '');

        $alerts = iterator_to_array(($this->listAlerts)($filters), false);

        return response()->json([
            'data' => array_map(
                static fn ($alert) => TrackingAlertResource::fromAlert($alert)->toArray(),
                $alerts
            ),
        ]);
    }
}
