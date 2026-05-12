<?php

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders;

use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Fulfillment\Orders\ShipmentOrderItem;
use App\Domain\Fulfillment\Orders\ShipmentOrderPaginationResult;
use App\Domain\Fulfillment\Orders\ShipmentPackage;
use App\Domain\Fulfillment\Orders\ValueObjects\ShipmentReceiverAddress;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\FulfillmentSequenceModel;
use App\Support\Persistence\CastsDateTime;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

final class EloquentShipmentOrderRepository implements ShipmentOrderRepository
{
    use CastsDateTime;

    public function paginate(int $page, int $perPage, array $filters = []): ShipmentOrderPaginationResult
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $query = ShipmentOrderModel::query()
            ->with(['items', 'packages', 'shipments']);

        if (isset($filters['is_booked'])) {
            $query->where('is_booked', $filters['is_booked']);
        }

        if (($filters['filter'] ?? null) === 'booked') {
            $query->where('is_booked', true);
        } elseif (($filters['filter'] ?? null) === 'unbooked') {
            $query->where('is_booked', false);
        } elseif (($filters['filter'] ?? null) === 'recent' && ($filters['processed_from'] ?? null) === null) {
            $recentFrom = CarbonImmutable::now()->subDays(7);
            $query->where('processed_at', '>=', $recentFrom);
        }

        if (($filters['sender_code'] ?? null) !== null) {
            $query->where('sender_code', $filters['sender_code']);
        }

        if (($filters['destination_country'] ?? null) !== null) {
            $query->where('destination_country', strtoupper($filters['destination_country']));
        }

        if (($filters['processed_from'] ?? null) instanceof DateTimeInterface) {
            $query->where('processed_at', '>=', $filters['processed_from']);
        }

        if (($filters['processed_to'] ?? null) instanceof DateTimeInterface) {
            $query->where('processed_at', '<=', $filters['processed_to']);
        }

        $search = $filters['search'] ?? null;
        if ($search !== null) {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';

                $builder->where('contact_email', 'like', $like)
                    ->orWhere('sender_code', 'like', $like)
                    ->orWhere('contact_phone', 'like', $like)
                    ->orWhere('destination_country', 'like', $like)
                    ->orWhere('order_type', 'like', $like);

                if (preg_match('/\d+/', $search, $match)) {
                    $numeric = (int) $match[0];
                    $builder->orWhere('external_order_id', $numeric)
                        ->orWhere('customer_number', $numeric)
                        ->orWhere('plenty_order_id', $numeric);
                }

                $builder->orWhereHas('shipments', static function (Builder $shipments) use ($like): void {
                    $shipments->where('tracking_number', 'like', $like);
                });
            });
        }

        $sortColumn = $filters['sort_column'] ?? null;
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        if ($sortColumn === 'tracking_sort') {
            $query->select('shipment_orders.*')
                ->selectSub(function ($sub) {
                    $sub->from('shipment_order_shipments as sos')
                        ->join('shipments as s', 's.id', '=', 'sos.shipment_id')
                        ->whereColumn('sos.shipment_order_id', 'shipment_orders.id')
                        ->selectRaw('MIN(s.tracking_number)');
                }, 'tracking_sort');
        }

        if ($sortColumn !== null) {
            $query->orderBy($sortColumn, $sortDirection)
                ->orderByDesc('id');
        } else {
            $query->orderByDesc('processed_at')
                ->orderByDesc('id');
        }

        $total = (clone $query)->count();
        $rows = $query
            ->forPage($page, $perPage)
            ->get();

        $orders = $rows->map(fn (ShipmentOrderModel $model) => $this->mapOrder($model))->all();

        return new ShipmentOrderPaginationResult($orders, $page, $perPage, $total);
    }

    public function getById(Identifier $id): ?ShipmentOrder
    {
        $model = ShipmentOrderModel::query()
            ->with(['items', 'packages', 'shipments'])
            ->find($id->toInt());

        return $model ? $this->mapOrder($model) : null;
    }

    public function getByExternalOrderId(int $externalOrderId): ?ShipmentOrder
    {
        $model = ShipmentOrderModel::query()
            ->with(['items', 'packages', 'shipments'])
            ->where('external_order_id', $externalOrderId)
            ->first();

        return $model ? $this->mapOrder($model) : null;
    }

    public function nextIdentity(): Identifier
    {
        $next = FulfillmentSequenceModel::reserveNextId(
            FulfillmentSequenceModel::ORDER_SEQUENCE,
            static fn (): int => ((int) (ShipmentOrderModel::query()->max('id') ?? 0)) + 1,
            static fn (int $candidate): bool => ShipmentOrderModel::query()->whereKey($candidate)->exists(),
        );

        return Identifier::fromInt($next);
    }

    public function save(ShipmentOrder $order): void
    {
        $connection = ShipmentOrderModel::query()->getConnection();

        $connection->transaction(function () use ($order): void {
            $model = ShipmentOrderModel::query()
                ->lockForUpdate()
                ->find($order->id()->toInt());

            if ($model === null) {
                $model = new ShipmentOrderModel;
                $model->setAttribute('id', $order->id()->toInt());
                $model->setAttribute('created_at', $order->createdAt());
            }

            $model->external_order_id = $order->externalOrderId();
            $model->customer_number = $order->customerNumber();
            $model->plenty_order_id = $order->plentyOrderId();
            $model->order_type = $order->orderType();
            $model->sender_profile_id = $order->senderProfileId()?->toInt();
            $model->sender_code = $order->senderCode();
            $model->freight_profile_id = $order->freightProfileId();
            $model->contact_email = $order->contactEmail();
            $model->contact_phone = $order->contactPhone();
            $model->destination_country = $order->destinationCountry();
            $model->currency = $order->currency();
            $model->total_amount = $order->totalAmount();
            $model->processed_at = $order->processedAt();
            $model->is_booked = $order->isBooked();
            $model->booked_at = $order->bookedAt();
            $model->booked_by = $order->bookedBy();
            $model->shipped_at = $order->shippedAt();
            $model->last_export_filename = $order->lastExportFilename();
            $model->metadata = $order->metadata();
            $model->dhl_shipment_id = $order->dhlShipmentId();
            $model->dhl_label_url = $order->dhlLabelUrl();
            $model->dhl_label_pdf_base64 = $order->dhlLabelPdfBase64();
            $model->dhl_pickup_reference = $order->dhlPickupReference();
            $model->dhl_product_id = $order->dhlProductId();
            $model->dhl_booking_payload = $order->dhlBookingPayload();
            $model->dhl_booking_response = $order->dhlBookingResponse();
            $model->dhl_booking_error = $order->dhlBookingError();
            $model->dhl_booked_at = $order->dhlBookedAt();
            $model->dhl_cancelled_at = $order->dhlCancelledAt();
            $model->dhl_cancelled_by = $order->dhlCancelledBy();
            $model->dhl_cancellation_reason = $order->dhlCancellationReason();

            $receiver = $order->receiverAddress();
            if ($receiver !== null) {
                $model->receiver_company_name = $receiver->companyName();
                $model->receiver_contact_name = $receiver->contactName();
                $model->receiver_street = $receiver->street();
                $model->receiver_additional_address_info = $receiver->additionalAddressInfo();
                $model->receiver_postal_code = $receiver->postalCode();
                $model->receiver_city_name = $receiver->cityName();
                $model->receiver_country_code = $receiver->countryCode();
                $model->receiver_email = $receiver->email();
                $model->receiver_phone = $receiver->phone();
            }
            $model->setAttribute('updated_at', $order->updatedAt());
            $model->save();

            $orderId = (int) $model->getKey();

            $this->syncItems($orderId, $order->items());
            $this->syncPackages($orderId, $order->packages());
            $this->syncShipments($orderId, $order->trackingNumbers());
        });
    }

    private function mapOrder(ShipmentOrderModel $model): ShipmentOrder
    {
        $orderId = Identifier::fromInt((int) $model->getKey());

        $items = $model->items->map(
            fn (ShipmentOrderItemModel $item) => ShipmentOrderItem::hydrate(
                Identifier::fromInt((int) $item->getKey()),
                $orderId,
                $item->item_id !== null ? (int) $item->item_id : null,
                $item->variation_id !== null ? (int) $item->variation_id : null,
                $item->sku,
                $item->description,
                (int) $item->quantity,
                $item->packaging_profile_id !== null ? Identifier::fromInt((int) $item->packaging_profile_id) : null,
                $item->weight_kg !== null ? (float) $item->weight_kg : null,
                (bool) $item->is_assembly,
            )
        )->all();

        $packages = $model->packages->map(
            fn (ShipmentPackageModel $package) => ShipmentPackage::hydrate(
                Identifier::fromInt((int) $package->getKey()),
                $orderId,
                $package->packaging_profile_id !== null ? Identifier::fromInt((int) $package->packaging_profile_id) : null,
                $package->package_reference,
                (int) $package->quantity,
                $package->weight_kg !== null ? (float) $package->weight_kg : null,
                $package->length_mm !== null ? (int) $package->length_mm : null,
                $package->width_mm !== null ? (int) $package->width_mm : null,
                $package->height_mm !== null ? (int) $package->height_mm : null,
                (int) $package->truck_slot_units,
            )
        )->all();

        $trackingNumbers = $model->shipments->pluck('tracking_number')->filter()->map(fn ($value) => (string) $value)->all();

        $receiverAddress = $this->mapReceiverAddress($model);

        return ShipmentOrder::hydrate(
            $orderId,
            (int) $model->external_order_id,
            $model->customer_number !== null ? (int) $model->customer_number : null,
            $model->plenty_order_id !== null ? (int) $model->plenty_order_id : null,
            $model->order_type,
            $model->sender_profile_id !== null ? Identifier::fromInt((int) $model->sender_profile_id) : null,
            $model->sender_code,
            $model->contact_email,
            $model->contact_phone,
            $model->destination_country,
            $model->currency ?? 'EUR',
            $model->total_amount !== null ? (float) $model->total_amount : null,
            $this->toImmutable($model->processed_at),
            (bool) $model->is_booked,
            $this->toImmutable($model->booked_at),
            $model->booked_by,
            $this->toImmutable($model->shipped_at),
            $model->last_export_filename,
            $items,
            $packages,
            $trackingNumbers,
            is_array($model->metadata) ? $model->metadata : [],
            $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
            $this->toImmutable($model->updated_at) ?? new DateTimeImmutable,
            $model->dhl_shipment_id,
            $model->dhl_label_url,
            $model->dhl_label_pdf_base64,
            $model->dhl_pickup_reference,
            $model->dhl_product_id,
            is_array($model->dhl_booking_payload) ? $model->dhl_booking_payload : [],
            is_array($model->dhl_booking_response) ? $model->dhl_booking_response : [],
            $model->dhl_booking_error,
            $this->toImmutable($model->dhl_booked_at),
            $model->dhl_cancelled_at?->format('Y-m-d H:i:s'),
            $model->dhl_cancelled_by,
            $model->dhl_cancellation_reason,
            $receiverAddress,
            $model->freight_profile_id !== null ? (int) $model->freight_profile_id : null,
        );
    }

    /**
     * Builds the {@see ShipmentReceiverAddress} VO from first-class columns.
     * Backward-compat: if columns are empty but the legacy metadata['receiver'] JSON
     * structure is present, hydrate from there. Invalid legacy payloads silently
     * yield null (lazy-migration pre-condition is the dedicated migration backfill).
     */
    private function mapReceiverAddress(ShipmentOrderModel $model): ?ShipmentReceiverAddress
    {
        $street = $model->getAttribute('receiver_street');
        $postal = $model->getAttribute('receiver_postal_code');
        $city = $model->getAttribute('receiver_city_name');
        $country = $model->getAttribute('receiver_country_code');

        if (is_string($street) && $street !== ''
            && is_string($postal) && $postal !== ''
            && is_string($city) && $city !== ''
            && is_string($country) && $country !== ''
        ) {
            try {
                return ShipmentReceiverAddress::create(
                    $street,
                    $postal,
                    $city,
                    $country,
                    $model->getAttribute('receiver_company_name'),
                    $model->getAttribute('receiver_contact_name'),
                    $model->getAttribute('receiver_additional_address_info'),
                    $model->getAttribute('receiver_email'),
                    $model->getAttribute('receiver_phone'),
                );
            } catch (\InvalidArgumentException) {
                // Fall through to legacy metadata path.
            }
        }

        $metadata = is_array($model->metadata) ? $model->metadata : [];
        $receiver = $metadata['receiver'] ?? null;
        if (! is_array($receiver)) {
            return null;
        }

        $legacyStreet = trim(implode(' ', array_filter([
            isset($receiver['streetName']) ? (string) $receiver['streetName'] : null,
            isset($receiver['streetNumber']) ? (string) $receiver['streetNumber'] : null,
        ], static fn ($v): bool => $v !== null && $v !== '')));
        $legacyPostal = isset($receiver['postalCode']) ? trim((string) $receiver['postalCode']) : '';
        $legacyCity = isset($receiver['city']) ? trim((string) $receiver['city']) : '';
        $legacyCountry = isset($receiver['countryIso2']) ? strtoupper(trim((string) $receiver['countryIso2'])) : '';

        if ($legacyStreet === '' || $legacyPostal === '' || $legacyCity === '' || strlen($legacyCountry) !== 2) {
            return null;
        }

        try {
            return ShipmentReceiverAddress::create(
                mb_substr($legacyStreet, 0, 50),
                mb_substr($legacyPostal, 0, 10),
                mb_substr($legacyCity, 0, 35),
                $legacyCountry,
                isset($receiver['companyName']) ? mb_substr((string) $receiver['companyName'], 0, 50) : null,
                isset($receiver['contactPerson']) ? mb_substr((string) $receiver['contactPerson'], 0, 50) : null,
                isset($receiver['additionalAddressInfo']) ? mb_substr((string) $receiver['additionalAddressInfo'], 0, 50) : null,
                isset($receiver['email']) ? (string) $receiver['email'] : null,
                isset($receiver['phone']) ? (string) $receiver['phone'] : null,
            );
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @param  array<int,ShipmentOrderItem>  $items
     */
    private function syncItems(int $orderId, array $items): void
    {
        $incomingIds = [];

        foreach ($items as $item) {
            $itemId = $item->id()->toInt();
            $incomingIds[] = $itemId;

            $model = ShipmentOrderItemModel::find($itemId) ?? new ShipmentOrderItemModel;
            $model->setAttribute('id', $itemId);
            $model->shipment_order_id = $orderId;
            $model->item_id = $item->itemId();
            $model->variation_id = $item->variationId();
            $model->sku = $item->sku();
            $model->description = $item->description();
            $model->quantity = $item->quantity();
            $model->packaging_profile_id = $item->packagingProfileId()?->toInt();
            $model->weight_kg = $item->weightKg();
            $model->is_assembly = $item->isAssembly();
            $model->metadata = null;
            $model->save();
        }

        ShipmentOrderItemModel::query()
            ->where('shipment_order_id', $orderId)
            ->when($incomingIds !== [], fn ($query) => $query->whereNotIn('id', $incomingIds))
            ->delete();
    }

    /**
     * @param  array<int,ShipmentPackage>  $packages
     */
    private function syncPackages(int $orderId, array $packages): void
    {
        $incomingIds = [];

        foreach ($packages as $package) {
            $packageId = $package->id()->toInt();
            $incomingIds[] = $packageId;

            $model = ShipmentPackageModel::find($packageId) ?? new ShipmentPackageModel;
            $model->setAttribute('id', $packageId);
            $model->shipment_order_id = $orderId;
            $model->packaging_profile_id = $package->packagingProfileId()?->toInt();
            $model->package_reference = $package->packageReference();
            $model->quantity = $package->quantity();
            $model->weight_kg = $package->weightKg();
            $model->length_mm = $package->lengthMillimetres();
            $model->width_mm = $package->widthMillimetres();
            $model->height_mm = $package->heightMillimetres();
            $model->truck_slot_units = $package->truckSlotUnits();
            $model->metadata = null;
            $model->save();
        }

        ShipmentPackageModel::query()
            ->where('shipment_order_id', $orderId)
            ->when($incomingIds !== [], fn ($query) => $query->whereNotIn('id', $incomingIds))
            ->delete();
    }

    /**
     * @param  array<int,string>  $trackingNumbers
     */
    private function syncShipments(int $orderId, array $trackingNumbers): void
    {
        $trackingNumbers = array_values(array_filter(array_map('trim', $trackingNumbers)));

        if ($trackingNumbers === []) {
            ShipmentOrderShipmentModel::query()
                ->where('shipment_order_id', $orderId)
                ->delete();

            return;
        }

        $shipmentIds = ShipmentModel::query()
            ->whereIn('tracking_number', $trackingNumbers)
            ->pluck('id', 'tracking_number')
            ->map(fn ($value) => (int) $value)
            ->all();

        $linked = [];

        foreach ($trackingNumbers as $trackingNumber) {
            $shipmentId = $shipmentIds[$trackingNumber] ?? null;
            if (! $shipmentId) {
                continue;
            }

            ShipmentOrderShipmentModel::query()->updateOrCreate(
                [
                    'shipment_order_id' => $orderId,
                    'shipment_id' => $shipmentId,
                ]
            );

            $linked[] = $shipmentId;
        }

        ShipmentOrderShipmentModel::query()
            ->where('shipment_order_id', $orderId)
            ->when($linked !== [], fn ($query) => $query->whereNotIn('shipment_id', $linked))
            ->delete();
    }

    public function linkShipment(Identifier $orderId, Identifier $shipmentId): void
    {
        ShipmentOrderShipmentModel::query()->updateOrCreate(
            [
                'shipment_order_id' => $orderId->toInt(),
                'shipment_id' => $shipmentId->toInt(),
            ]
        );
    }
}
