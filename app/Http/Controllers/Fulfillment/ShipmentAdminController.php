<?php

namespace App\Http\Controllers\Fulfillment;

use App\Application\Fulfillment\Shipments\Queries\ListShipments;
use App\Application\Fulfillment\Shipments\Resources\ShipmentDetailResource;
use App\Application\Fulfillment\Shipments\ShipmentTrackingService;
use App\Domain\Fulfillment\Shipments\Shipment;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final class ShipmentAdminController
{
    public function __construct(
        private readonly ListShipments $listShipments,
        private readonly ShipmentTrackingService $tracking,
    ) {}

    public function index(Request $request): View
    {
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = max(1, min(100, (int) $request->integer('per_page', 25)));

        $filters = [];

        $carrier = trim((string) $request->input('carrier', ''));
        if ($carrier !== '') {
            $filters['carrier'] = $carrier;
        }

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $filters['status'] = $status;
        }

        $dateFromRaw = $this->sanitizeDateInput($request->input('date_from'));
        if ($dateFromRaw !== null) {
            $filters['date_from'] = $this->parseDate($dateFromRaw);
        }

        $dateToRaw = $this->sanitizeDateInput($request->input('date_to'));
        if ($dateToRaw !== null) {
            $filters['date_to'] = $this->parseDate($dateToRaw);
        }

        $pagination = ($this->listShipments)(
            $page,
            $perPage,
            Arr::where($filters, static fn ($value) => $value !== null)
        );

        $shipments = array_map(
            static fn (Shipment $shipment) => ShipmentDetailResource::fromShipment($shipment)->toArray(),
            $pagination->shipments
        );

        $allEvents = [];
        $syncHistory = [];

        foreach ($shipments as $shipment) {
            foreach ($shipment['events'] as $event) {
                $eventWithShipment = array_merge(
                    $event,
                    [
                        'shipment' => [
                            'id' => $shipment['id'],
                            'tracking_number' => $shipment['tracking_number'],
                            'carrier_code' => $shipment['carrier_code'],
                            'status_code' => $shipment['status_code'],
                        ],
                    ]
                );

                $allEvents[] = $eventWithShipment;

                if (($event['event_code'] ?? null) === 'MANUAL_SYNC') {
                    $syncHistory[] = $eventWithShipment;
                }
            }
        }

        usort($allEvents, static fn ($a, $b) => strcmp($b['occurred_at'] ?? '', $a['occurred_at'] ?? ''));
        usort($syncHistory, static fn ($a, $b) => strcmp($b['occurred_at'] ?? '', $a['occurred_at'] ?? ''));

        $activeTab = $request->string('tab')->trim()->toString();
        if (! in_array($activeTab, ['overview', 'events', 'sync-history'], true)) {
            $activeTab = 'overview';
        }

        $tabs = [
            'overview' => [
                'label' => 'Übersicht',
                'badge' => count($shipments),
            ],
            'events' => [
                'label' => 'Events',
                'badge' => count($allEvents),
            ],
            'sync-history' => [
                'label' => 'Sync-Historie',
                'badge' => count($syncHistory),
            ],
        ];

        return view('fulfillment.shipments.index', [
            'pagination' => $pagination,
            'shipments' => $shipments,
            'events' => $allEvents,
            'syncHistory' => $syncHistory,
            'filters' => [
                'carrier' => $carrier,
                'status' => $status,
                'date_from' => $dateFromRaw,
                'date_to' => $dateToRaw,
                'per_page' => $perPage,
            ],
            'tabs' => $tabs,
            'activeTab' => $activeTab,
            'baseUrl' => $request->url(),
        ]);
    }

    public function sync(Request $request, int $shipment): RedirectResponse
    {
        $initiator = $this->resolveInitiator($request);
        $note = trim((string) $request->input('note', '')) ?: null;

        try {
            $this->tracking->triggerManualSync($shipment, $initiator, $note);
            $message = ['success' => 'Manueller Sync wurde ausgelöst.'];
        } catch (\Throwable $exception) {
            report($exception);
            $message = ['error' => 'Sync konnte nicht ausgelöst werden: '.$exception->getMessage()];
        }

        $query = $request->except(['_token', 'note']);

        return redirect()
            ->route('fulfillment-shipments', $query)
            ->with($message);
    }

    private function resolveInitiator(Request $request): string
    {
        $user = $request->user();
        if ($user && method_exists($user, 'getAuthIdentifier')) {
            return (string) ($user->name ?? $user->email ?? $user->getAuthIdentifier());
        }

        return 'admin';
    }

    private function sanitizeDateInput(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
