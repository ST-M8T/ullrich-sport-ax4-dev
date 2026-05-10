<?php

namespace App\Http\Controllers\Fulfillment;

use App\Application\Fulfillment\Orders\ShipmentOrderAdministrationService;
use App\Http\Requests\Fulfillment\ShipmentOrderBulkSyncRequest;
use App\Http\Requests\Fulfillment\ShipmentOrderManualSyncRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

final class ShipmentOrderActionController
{
    public function __construct(
        private readonly ShipmentOrderAdministrationService $administration,
    ) {}

    public function syncVisible(ShipmentOrderBulkSyncRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $scope = $validated['scope'] ?? 'page';
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 25);
        $filters = $this->buildFilters($validated);

        $summary = $this->administration->syncVisible($filters, $scope, $page, $perPage);

        $successMessage = sprintf(
            '%d Aufträge synchronisiert (%d neu, %d aktualisiert).',
            $summary['synced'],
            $summary['created'],
            $summary['updated']
        );

        $response = $this->redirectBack($request);

        if (count($summary['errors']) > 0) {
            $preview = $this->formatErrors($summary['errors']);

            return $response->withErrors([
                'sync_visible' => $successMessage.' Fehler: '.$preview,
            ]);
        }

        return $response->with('success', $successMessage);
    }

    public function syncBooked(ShipmentOrderBulkSyncRequest $request): RedirectResponse
    {
        $summary = $this->administration->syncBooked();

        $message = sprintf(
            '%d Aufträge für Tracking übertragen. Ereignisse: %d.',
            $summary['processed'],
            $summary['tracking_events']
        );

        $response = $this->redirectBack($request);

        if (count($summary['errors']) > 0) {
            $preview = $this->formatErrors($summary['errors']);

            return $response->withErrors([
                'sync_booked' => $message.' Fehler: '.$preview,
            ]);
        }

        return $response->with('success', $message);
    }

    public function manualSync(ShipmentOrderManualSyncRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $orderId = (int) $validated['manual_order_id'];
        $tracking = $validated['manual_tracking'] ?? null;
        $syncImmediately = in_array($validated['manual_sync'] ?? '0', ['1', 'true'], true);

        $result = $this->administration->manualSync($orderId, $tracking, $syncImmediately);
        $summary = $result['summary'];

        $message = sprintf(
            'Auftrag #%d synchronisiert (%d Fehler).',
            $orderId,
            count($summary['errors'])
        );

        if ($result['linked_shipment_id']) {
            $message .= sprintf(' Shipment #%d verknüpft.', $result['linked_shipment_id']);
        }

        if ($result['tracking_transferred']) {
            $message .= ' Tracking-Transfer ausgelöst.';
        }

        $response = $this->redirectBack($request);

        if (count($summary['errors']) > 0) {
            $preview = $this->formatErrors($summary['errors']);

            return $response->withErrors([
                'manual_sync' => $message.' Fehler: '.$preview,
            ]);
        }

        return $response->with('success', $message);
    }

    /**
     * @param  array<string,mixed>  $validated
     * @return array<string,mixed>
     */
    private function buildFilters(array $validated): array
    {
        $filters = [
            'filter' => $validated['filter'] ?? null,
            'search' => $validated['search'] ?? null,
            'sort' => $validated['sort'] ?? null,
            'direction' => strtolower((string) ($validated['dir'] ?? 'desc')),
        ];

        if (! in_array($filters['direction'], ['asc', 'desc'], true)) {
            $filters['direction'] = 'desc';
        }

        if (! empty($validated['sender_code'])) {
            $filters['sender_code'] = $validated['sender_code'];
        }

        if (! empty($validated['destination_country'])) {
            $filters['destination_country'] = strtoupper($validated['destination_country']);
        }

        if (array_key_exists('is_booked', $validated) && $validated['is_booked'] !== null && $validated['is_booked'] !== '') {
            $filters['is_booked'] = (bool) (int) $validated['is_booked'];
        }

        $timezone = config('app.timezone');

        if (! empty($validated['processed_from'])) {
            $from = CarbonImmutable::createFromFormat('Y-m-d', $validated['processed_from'], $timezone);
            if ($from !== null) {
                $filters['processed_from'] = $from->startOfDay()->toDateTimeImmutable();
            }
        }

        if (! empty($validated['processed_to'])) {
            $to = CarbonImmutable::createFromFormat('Y-m-d', $validated['processed_to'], $timezone);
            if ($to !== null) {
                $filters['processed_to'] = $to->endOfDay()->toDateTimeImmutable();
            }
        }

        return array_filter(
            $filters,
            static fn ($value) => $value !== null && $value !== ''
        );
    }

    private function redirectBack(ShipmentOrderBulkSyncRequest|ShipmentOrderManualSyncRequest $request): RedirectResponse
    {
        $defaultRedirect = route('fulfillment-orders', array_filter([
            'filter' => $request->input('filter'),
            'search' => $request->input('search'),
        ]));

        return redirect()->to($request->input('redirect_to', $defaultRedirect));
    }

    /**
     * @param  array<int,string>  $errors
     */
    private function formatErrors(array $errors, int $limit = 5): string
    {
        $preview = array_slice($errors, 0, $limit, true);

        $parts = [];
        foreach ($preview as $id => $error) {
            $parts[] = sprintf('#%d: %s', $id, mb_strimwidth($error, 0, 120, '…'));
        }

        if (count($errors) > $limit) {
            $parts[] = sprintf('… (%d weitere)', count($errors) - $limit);
        }

        return implode(', ', $parts);
    }
}
