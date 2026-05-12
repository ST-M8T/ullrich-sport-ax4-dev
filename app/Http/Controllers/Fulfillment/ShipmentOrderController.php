<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fulfillment;

use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlBookingOptions;
use App\Application\Fulfillment\Integrations\Dhl\Services\DhlCancellationService;
use App\Application\Fulfillment\Integrations\Dhl\Services\DhlLabelService;
use App\Application\Fulfillment\Integrations\Dhl\Services\DhlPriceQuoteService;
use App\Application\Fulfillment\Integrations\Dhl\Services\DhlShipmentBookingService;
use App\Application\Fulfillment\Masterdata\Services\FreightProfileService;
use App\Application\Fulfillment\Masterdata\Services\SenderProfileService;
use App\Application\Fulfillment\Orders\Commands\AssignShipmentOrderSenderProfile;
use App\Application\Fulfillment\Orders\Commands\BookShipmentOrder;
use App\Application\Fulfillment\Orders\Commands\TransferShipmentOrderTracking;
use App\Application\Fulfillment\Orders\Packaging\RecalculateOrderPackages;
use App\Application\Fulfillment\Orders\Queries\ListShipmentOrders;
use App\Application\Fulfillment\Orders\Queries\ShipmentOrderViewService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Http\Requests\Fulfillment\AssignShipmentOrderSenderProfileRequest;
use App\Http\Requests\Fulfillment\DhlBookingRequest;
use App\Http\Requests\Fulfillment\ShipmentOrderBookingRequest;
use App\Http\Requests\Fulfillment\ShipmentOrderIndexRequest;
use App\Http\Requests\Fulfillment\ShipmentOrderTrackingTransferRequest;
use App\ViewHelpers\Fulfillment\ShipmentTrackingViewHelper;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ShipmentOrderController
{
    public function __construct(
        private readonly ListShipmentOrders $listOrders,
        private readonly ShipmentOrderViewService $orderViews,
        private readonly SenderProfileService $senderProfiles,
        private readonly FreightProfileService $freightProfiles,
        private readonly AssignShipmentOrderSenderProfile $assignShipmentOrderSenderProfile,
        private readonly BookShipmentOrder $bookShipmentOrder,
        private readonly TransferShipmentOrderTracking $transferShipmentOrderTracking,
        private readonly DhlShipmentBookingService $dhlBookingService,
        private readonly DhlLabelService $dhlLabelService,
        private readonly DhlPriceQuoteService $dhlPriceQuoteService,
        private readonly DhlCancellationService $dhlCancellationService,
        private readonly RecalculateOrderPackages $recalculateOrderPackages,
    ) {
        // Dependencies are injected; no additional setup needed.
    }

    public function recalculatePackages(int $order): RedirectResponse
    {
        $count = $this->recalculateOrderPackages->execute(Identifier::fromInt($order));

        if ($count === 0) {
            return redirect()
                ->route('fulfillment-orders.show', $order)
                ->with('warning', 'Keine Pakete berechenbar — bitte Stammdaten (Variations- und Verpackungsprofil) prüfen.');
        }

        return redirect()
            ->route('fulfillment-orders.show', $order)
            ->with('success', $count.' Paket(e) automatisch aus Stammdaten berechnet.');
    }

    public function index(ShipmentOrderIndexRequest $request): View
    {
        $validated = $request->validated();

        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($validated['per_page'] ?? 25)));
        $filter = $validated['filter'] ?? null;
        $search = $validated['search'] ?? null;
        $sort = $validated['sort'] ?? 'processed_at';
        $direction = strtolower((string) ($validated['dir'] ?? 'desc'));
        if (in_array($direction, ['asc', 'desc'], true) === false) {
            $direction = 'desc';
        }

        $filters = [
            'filter' => $filter,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ];

        $senderCode = $validated['sender_code'] ?? '';
        if ($senderCode !== '') {
            $filters['sender_code'] = $senderCode;
        }

        $destinationCountry = $validated['destination_country'] ?? '';
        if ($destinationCountry !== '') {
            $filters['destination_country'] = strtoupper($destinationCountry);
        }

        if (array_key_exists('is_booked', $validated) && $validated['is_booked'] !== null && $validated['is_booked'] !== '') {
            $filters['is_booked'] = (bool) (int) $validated['is_booked'];
        }

        $timezone = config('app.timezone');

        $processedFrom = $validated['processed_from'] ?? '';
        if ($processedFrom !== '') {
            $from = CarbonImmutable::createFromFormat('Y-m-d', $processedFrom, $timezone);
            if ($from !== null) {
                $filters['processed_from'] = $from->startOfDay()->toDateTimeImmutable();
            }
        }

        $processedTo = $validated['processed_to'] ?? '';
        if ($processedTo !== '') {
            $to = CarbonImmutable::createFromFormat('Y-m-d', $processedTo, $timezone);
            if ($to !== null) {
                $filters['processed_to'] = $to->endOfDay()->toDateTimeImmutable();
            }
        }

        $pagination = ($this->listOrders)($page, $perPage, $filters);

        $expandId = isset($validated['expand']) ? (int) $validated['expand'] : null;
        $expandedOrder = null;
        $expandedItems = [];
        $expandedPackages = [];
        $expandedWeight = 0.0;

        if ($expandId !== null && $expandId > 0) {
            foreach ($pagination->orders as $order) {
                if ($order->externalOrderId() === $expandId) {
                    $expandedOrder = $order;
                    foreach ($order->items() as $item) {
                        $itemWeight = ($item->weightKg() ?? 0.0) * max(1, $item->quantity());
                        $expandedWeight += $itemWeight;
                        $expandedItems[] = [
                            'sku' => $item->sku(),
                            'description' => $item->description(),
                            'quantity' => $item->quantity(),
                            'weight' => $item->weightKg(),
                            'is_assembly' => $item->isAssembly(),
                            'packaging_profile_id' => $item->packagingProfileId()?->toInt(),
                        ];
                    }

                    foreach ($order->packages() as $package) {
                        $expandedPackages[] = [
                            'reference' => $package->packageReference(),
                            'quantity' => $package->quantity(),
                            'weight' => $package->weightKg(),
                            'dimensions' => [
                                $package->lengthMillimetres(),
                                $package->widthMillimetres(),
                                $package->heightMillimetres(),
                            ],
                            'truck_slots' => $package->truckSlotUnits(),
                        ];
                    }

                    break;
                }
            }
        }

        return view('fulfillment.orders.index', [
            'pagination' => $pagination,
            'query' => [
                'filter' => $filter,
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'sender_code' => $senderCode,
                'destination_country' => strtoupper($destinationCountry),
                'processed_from' => $processedFrom,
                'processed_to' => $processedTo,
                'is_booked' => $filters['is_booked'] ?? null,
            ],
            'perPage' => $perPage,
            'expanded' => [
                'order' => $expandedOrder,
                'items' => $expandedItems,
                'packages' => $expandedPackages,
                'weight' => $expandedWeight,
                'expand_id' => $expandId,
            ],
        ]);
    }

    public function show(int $order): View
    {
        $identifier = Identifier::fromInt($order);
        $details = $this->orderViews->getOrderWithShipments($identifier);
        abort_if($details === null, 404);

        // Transform shipments to include German labels and chronological event order
        $shipmentsWithLabels = array_map(
            static fn ($shipment) => ShipmentTrackingViewHelper::toOrderDetailArray($shipment),
            $details['shipments']
        );

        return view('fulfillment.orders.show', [
            'order' => $details['order'],
            'shipments' => $details['shipments'],
            'shipmentsWithLabels' => $shipmentsWithLabels,
            'senderProfiles' => $this->senderProfiles->all(),
            'freightProfiles' => $this->freightProfiles->all(),
        ]);
    }

    public function assignSenderProfile(
        AssignShipmentOrderSenderProfileRequest $request,
        int $order
    ): RedirectResponse {
        $identifier = Identifier::fromInt($order);
        $redirect = $request->input('redirect_to') ?? route('fulfillment-orders.show', ['order' => $order]);

        try {
            ($this->assignShipmentOrderSenderProfile)(
                $identifier,
                Identifier::fromInt((int) $request->validated('sender_profile_id')),
            );
        } catch (\Throwable $exception) {
            return redirect()->to($redirect)->withErrors([
                'sender_profile' => $exception->getMessage(),
            ])->withInput();
        }

        return redirect()
            ->to($redirect)
            ->with('success', 'Senderprofil wurde dem Auftrag zugeordnet.');
    }

    public function book(ShipmentOrderBookingRequest $request, int $order): RedirectResponse
    {
        $identifier = Identifier::fromInt($order);
        $redirect = $request->input('redirect_to') ?? route('fulfillment-orders.show', ['order' => $order]);

        try {
            ($this->bookShipmentOrder)(
                $identifier,
                optional($request->user())->name ?? optional($request->user())->email
            );
        } catch (\Throwable $exception) {
            return redirect()->to($redirect)->withErrors([
                'booking' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->to($redirect)
            ->with('success', 'Auftrag wurde erfolgreich gebucht.');
    }

    public function transfer(
        ShipmentOrderTrackingTransferRequest $request,
        int $order
    ): RedirectResponse {
        $identifier = Identifier::fromInt($order);
        $redirect = $request->input('redirect_to') ?? route('fulfillment-orders.show', ['order' => $order]);

        $trackingNumber = $request->input('tracking_number');
        $syncImmediately = (bool) ($request->validated()['sync_immediately'] ?? false);

        try {
            $transferred = ($this->transferShipmentOrderTracking)(
                $identifier,
                $trackingNumber ?: null,
                $syncImmediately
            );
        } catch (\Throwable $exception) {
            return redirect()->to($redirect)->withErrors([
                'tracking_transfer' => $exception->getMessage(),
            ])->withInput();
        }

        return redirect()
            ->to($redirect)
            ->with('success', 'Tracking-Transfer ausgelöst für: '.implode(', ', $transferred));
    }

    public function bookDhl(DhlBookingRequest $request, int $order): RedirectResponse
    {
        $identifier = Identifier::fromInt($order);
        $redirect = $request->input('redirect_to') ?? route('fulfillment-orders.show', ['order' => $order]);

        try {
            $validated = $request->validated();
            $options = DhlBookingOptions::fromArray([
                'product_id' => $validated['product_id'] ?? null,
                'product_code' => $validated['product_code'] ?? null,
                'payer_code' => $validated['payer_code'] ?? null,
                'default_package_type' => $validated['default_package_type'] ?? null,
                'additional_services' => $validated['additional_services'] ?? [],
                'pickup_date' => $validated['pickup_date'] ?? null,
                // Form-Override fuer Pieces (UI-Eingabe). Ohne diese Weitergabe
                // werden die im Formular gewaehlten Packstuecke verworfen
                // (siehe DhlPayloadAssembler::buildPieces — Override hat Vorrang
                // vor ShipmentOrder.packages()).
                'pieces' => $validated['pieces'] ?? null,
            ]);

            $result = $this->dhlBookingService->bookShipment($identifier, $options);

            if ($result->success === false) {
                return redirect()->to($redirect)->withErrors([
                    'dhl_booking' => $result->error ?? 'DHL-Buchung fehlgeschlagen.',
                ]);
            }

            $message = 'DHL-Buchung erfolgreich. Shipment-ID: '.$result->shipmentId;
            if (empty($result->trackingNumbers) === false) {
                $message .= ', Tracking: '.implode(', ', $result->trackingNumbers);
            }

            return redirect()
                ->to($redirect)
                ->with('success', $message);
        } catch (\Throwable $exception) {
            return redirect()->to($redirect)->withErrors([
                'dhl_booking' => $exception->getMessage(),
            ]);
        }
    }

    public function previewLabel(int $order): View|RedirectResponse
    {
        $identifier = Identifier::fromInt($order);
        $shipmentOrder = $this->orderViews->getOrder($identifier);
        abort_if($shipmentOrder === null, 404);

        if ($shipmentOrder->dhlShipmentId() === null) {
            return redirect()
                ->route('fulfillment-orders.show', ['order' => $order])
                ->withErrors(['label' => 'Kein DHL-Label vorhanden. Buchen Sie zuerst bei DHL.']);
        }

        $labelData = $this->getLabelData($identifier, $shipmentOrder);

        if ($labelData === null) {
            return redirect()
                ->route('fulfillment-orders.show', ['order' => $order])
                ->withErrors(['label' => 'Label konnte nicht geladen werden.']);
        }

        return view('fulfillment.orders.dhl.label-preview', [
            'order' => $shipmentOrder,
            'labelData' => $labelData,
            'downloadUrl' => route('fulfillment-orders.dhl.label.download', ['order' => $order]),
        ]);
    }

    public function downloadLabel(int $order): Response|RedirectResponse
    {
        $identifier = Identifier::fromInt($order);
        $shipmentOrder = $this->orderViews->getOrder($identifier);
        abort_if($shipmentOrder === null, 404);

        try {
            $pdfBase64 = $this->dhlLabelService->downloadLabelAsPdf($identifier);

            if ($pdfBase64 === null) {
                return redirect()
                    ->route('fulfillment-orders.show', ['order' => $order])
                    ->withErrors(['label' => 'Label konnte nicht generiert werden.']);
            }

            $pdfContent = base64_decode($pdfBase64, true);
            if ($pdfContent === false) {
                return redirect()
                    ->route('fulfillment-orders.show', ['order' => $order])
                    ->withErrors(['label' => 'Label-Daten sind ungültig.']);
            }

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="dhl-label-'.$order.'.pdf"',
            ]);
        } catch (\Throwable $exception) {
            return redirect()
                ->route('fulfillment-orders.show', ['order' => $order])
                ->withErrors(['label' => $exception->getMessage()]);
        }
    }

    /**
     * @return array{tracking_numbers: array<int,string>, product_id: ?string, pickup_reference: ?string, generated_at: ?string, label_url: ?string, label_pdf_base64: ?string}|null
     */
    private function getLabelData(Identifier $identifier, object $shipmentOrder): ?array
    {
        $labelPdfBase64 = $shipmentOrder->dhlLabelPdfBase64();
        $labelUrl = $shipmentOrder->dhlLabelUrl();

        if ($labelPdfBase64 === null && $labelUrl === null) {
            $result = $this->dhlLabelService->generateLabel($identifier);
            if ($result->success === false) {
                return null;
            }
            $labelPdfBase64 = $result->labelPdfBase64;
            $labelUrl = $result->labelUrl;
        }

        return [
            'tracking_numbers' => $shipmentOrder->trackingNumbers() ?? [],
            'product_id' => $shipmentOrder->dhlProductId(),
            'pickup_reference' => $shipmentOrder->dhlPickupReference(),
            'generated_at' => $shipmentOrder->updatedAt()->format('d.m.Y H:i'),
            'label_url' => $labelUrl,
            'label_pdf_base64' => $labelPdfBase64,
        ];
    }

    public function getPriceQuote(int $order): JsonResponse
    {
        $identifier = Identifier::fromInt($order);
        $shipmentOrder = $this->orderViews->getOrder($identifier);
        abort_if($shipmentOrder === null, 404);

        try {
            $productId = request()->input('product_id');
            $options = DhlBookingOptions::fromArray([
                'product_id' => $productId,
                'product_code' => $productId,
            ]);
            $result = $this->dhlPriceQuoteService->getPriceQuote($identifier, $options);

            if ($result->success === false) {
                return response()->json([
                    'success' => false,
                    'error' => $result->error ?? 'Preisabfrage fehlgeschlagen.',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'price' => $result->totalPrice,
                'currency' => $result->currency ?? 'EUR',
                'breakdown' => $result->breakdown,
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    public function cancelDhl(Request $request, int $order): RedirectResponse
    {
        $identifier = Identifier::fromInt($order);
        $redirect = $request->input('redirect_to') ?? route('fulfillment-orders.show', ['order' => $order]);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $reason = $validated['reason'] ?? 'No reason provided';
        $cancelledBy = $request->user()?->email ?? 'system';

        $result = $this->dhlCancellationService->cancel($order, $reason, $cancelledBy);

        if ($result->success === false) {
            return redirect()->to($redirect)->withErrors([
                'dhl_cancellation' => $result->error ?? 'DHL-Stornierung fehlgeschlagen.',
            ]);
        }

        return redirect()
            ->to($redirect)
            ->with('success', 'DHL-Sendung wurde storniert am '.$result->cancelledAt);
    }
}
