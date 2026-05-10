<?php

namespace App\Http\Controllers\Tracking;

use App\Application\Tracking\Queries\ListTrackingAlerts;
use App\Application\Tracking\TrackingAlertService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Tracking\TrackingAlert;
use App\Http\Controllers\Controller;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

final class TrackingAlertController extends Controller
{
    public function __construct(
        private readonly TrackingAlertService $alertService,
        private readonly ListTrackingAlerts $listAlerts,
    ) {}

    public function show(int $alert): JsonResponse
    {
        $identifier = Identifier::fromInt($alert);
        $trackingAlert = $this->alertService->get($identifier);

        if (! $trackingAlert) {
            abort(404, 'Tracking alert not found.');
        }

        $related = collect(iterator_to_array(($this->listAlerts)([
            'alert_type' => $trackingAlert->alertType(),
        ])))
            ->sortByDesc(fn (TrackingAlert $item) => $item->createdAt()->getTimestamp())
            ->take(10)
            ->map(fn (TrackingAlert $item) => $this->presentAlertSummary($item))
            ->values()
            ->all();

        return response()->json([
            'alert' => $this->presentAlertDetail($trackingAlert),
            'similar' => $related,
        ]);
    }

    public function acknowledge(int $alert): JsonResponse
    {
        $identifier = Identifier::fromInt($alert);
        $updated = $this->alertService->acknowledge($identifier);

        if (! $updated) {
            abort(404, 'Tracking alert not found.');
        }

        return response()->json([
            'alert' => $this->presentAlertDetail($updated),
            'message' => 'Alert wurde bestätigt.',
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function presentAlertDetail(TrackingAlert $alert): array
    {
        return [
            'id' => $alert->id()->toInt(),
            'alert_type' => $alert->alertType(),
            'severity' => $alert->severity(),
            'channel' => $alert->channel(),
            'message' => $alert->message(),
            'is_acknowledged' => $alert->isAcknowledged(),
            'is_sent' => $alert->isSent(),
            'created_at' => $this->formatDate($alert->createdAt()),
            'sent_at' => $this->formatDate($alert->sentAt()),
            'acknowledged_at' => $this->formatDate($alert->acknowledgedAt()),
            'metadata' => $alert->metadata(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function presentAlertSummary(TrackingAlert $alert): array
    {
        return [
            'id' => $alert->id()->toInt(),
            'alert_type' => $alert->alertType(),
            'severity' => $alert->severity(),
            'created_at' => $this->formatDate($alert->createdAt()),
            'acknowledged_at' => $this->formatDate($alert->acknowledgedAt()),
        ];
    }

    private function formatDate(?DateTimeImmutable $date): ?string
    {
        return $date?->format('d.m.Y H:i');
    }
}
