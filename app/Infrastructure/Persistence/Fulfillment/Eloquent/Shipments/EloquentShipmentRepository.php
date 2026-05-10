<?php

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent\Shipments;

use App\Domain\Fulfillment\Shipments\Contracts\ShipmentRepository;
use App\Domain\Fulfillment\Shipments\Shipment;
use App\Domain\Fulfillment\Shipments\ShipmentEvent;
use App\Domain\Fulfillment\Shipments\ShipmentPaginationResult;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\FulfillmentSequenceModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentEventModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentModel;
use App\Support\Persistence\CastsDateTime;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

final class EloquentShipmentRepository implements ShipmentRepository
{
    use CastsDateTime;

    /**
     * @param  array<string,mixed>  $filters
     */
    public function paginate(int $page, int $perPage, array $filters = []): ShipmentPaginationResult
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $query = ShipmentModel::query()
            ->with(['events' => fn ($q) => $q->orderByDesc('event_occurred_at')])
            ->when(isset($filters['carrier']), fn (Builder $q) => $q->where('carrier_code', $filters['carrier']))
            ->when(isset($filters['status']), fn (Builder $q) => $q->where('status_code', $filters['status']));

        $from = $this->normalizeDateFilter($filters['date_from'] ?? null);
        if ($from) {
            $query->where(function (Builder $q) use ($from) {
                $q->where(function (Builder $sub) use ($from) {
                    $sub->whereNotNull('last_event_at')
                        ->where('last_event_at', '>=', $from);
                })->orWhere(function (Builder $sub) use ($from) {
                    $sub->whereNull('last_event_at')
                        ->where('created_at', '>=', $from);
                });
            });
        }

        $to = $this->normalizeDateFilter($filters['date_to'] ?? null);
        if ($to) {
            $query->where(function (Builder $q) use ($to) {
                $q->where(function (Builder $sub) use ($to) {
                    $sub->whereNotNull('last_event_at')
                        ->where('last_event_at', '<=', $to);
                })->orWhere(function (Builder $sub) use ($to) {
                    $sub->whereNull('last_event_at')
                        ->where('created_at', '<=', $to);
                });
            });
        }

        $total = (clone $query)->count();

        /** @var \Illuminate\Database\Eloquent\Collection<int, ShipmentModel> $models */
        $models = $query
            ->orderByDesc('last_event_at')
            ->orderByDesc('created_at')
            ->forPage($page, $perPage)
            ->get();

        $shipments = $models
            ->map(fn (ShipmentModel $model) => $this->mapShipment($model))
            ->all();

        return new ShipmentPaginationResult($shipments, $page, $perPage, (int) $total);
    }

    public function getByTrackingNumber(string $trackingNumber): ?Shipment
    {
        $model = ShipmentModel::query()
            ->with('events')
            ->where('tracking_number', $trackingNumber)
            ->first();

        return $model ? $this->mapShipment($model) : null;
    }

    public function getById(Identifier $id): ?Shipment
    {
        $model = ShipmentModel::query()->with('events')->find($id->toInt());

        return $model ? $this->mapShipment($model) : null;
    }

    public function nextIdentity(): Identifier
    {
        $next = FulfillmentSequenceModel::reserveNextId(
            FulfillmentSequenceModel::SHIPMENT_SEQUENCE,
            static fn (): int => ((int) (ShipmentModel::query()->max('id') ?? 0)) + 1,
            static fn (int $candidate): bool => ShipmentModel::query()->whereKey($candidate)->exists(),
        );

        return Identifier::fromInt($next);
    }

    public function save(Shipment $shipment): void
    {
        $model = ShipmentModel::find($shipment->id()->toInt()) ?? new ShipmentModel(['id' => $shipment->id()->toInt()]);

        $model->carrier_code = $shipment->carrierCode();
        $model->shipping_profile_id = $shipment->shippingProfileId();
        $model->tracking_number = $shipment->trackingNumber();
        $model->status_code = $shipment->statusCode();
        $model->status_description = $shipment->statusDescription();
        $model->last_event_at = $shipment->lastEventAt();
        $model->delivered_at = $shipment->deliveredAt();
        $model->next_sync_after = $shipment->nextSyncAfter();
        $model->weight_kg = $shipment->weightKg();
        $model->volume_dm3 = $shipment->volumeDm3();
        $model->pieces_count = $shipment->piecesCount();
        $model->failed_attempts = $shipment->failedAttempts();
        $model->last_payload = $shipment->lastPayload();
        $model->metadata = $shipment->metadata();
        $model->save();
    }

    public function appendEvent(Identifier $shipmentId, ShipmentEvent $event): void
    {
        ShipmentEventModel::create([
            'id' => $event->id(),
            'shipment_id' => $shipmentId->toInt(),
            'event_code' => $event->eventCode(),
            'event_status' => $event->status(),
            'event_description' => $event->description(),
            'facility' => $event->facility(),
            'city' => $event->city(),
            'country_iso2' => $event->country(),
            'event_occurred_at' => $event->occurredAt(),
            'payload' => $event->payload(),
            'created_at' => $event->createdAt(),
        ]);
    }

    public function nextEventIdentity(Identifier $shipmentId): int
    {
        return FulfillmentSequenceModel::reserveNextId(
            FulfillmentSequenceModel::SHIPMENT_EVENT_SEQUENCE,
            static fn (): int => ((int) (ShipmentEventModel::query()->max('id') ?? 0)) + 1,
            static fn (int $candidate): bool => ShipmentEventModel::query()->whereKey($candidate)->exists(),
        );
    }

    private function mapShipment(ShipmentModel $model): Shipment
    {
        $events = $model->events
            ->sortByDesc('event_occurred_at')
            ->map(fn (ShipmentEventModel $event) => $this->mapEvent($event))
            ->values()
            ->all();

        return Shipment::hydrate(
            Identifier::fromInt((int) $model->getKey()),
            $model->carrier_code,
            $model->shipping_profile_id !== null ? (int) $model->shipping_profile_id : null,
            $model->tracking_number,
            $model->status_code,
            $model->status_description,
            $this->toImmutable($model->last_event_at),
            $this->toImmutable($model->delivered_at),
            $this->toImmutable($model->next_sync_after),
            $model->weight_kg !== null ? (float) $model->weight_kg : null,
            $model->volume_dm3 !== null ? (float) $model->volume_dm3 : null,
            $model->pieces_count !== null ? (int) $model->pieces_count : null,
            (int) $model->failed_attempts,
            is_array($model->last_payload) ? $model->last_payload : [],
            is_array($model->metadata) ? $model->metadata : [],
            $events,
            $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
            $this->toImmutable($model->updated_at) ?? new DateTimeImmutable,
        );
    }

    private function mapEvent(ShipmentEventModel $model): ShipmentEvent
    {
        return ShipmentEvent::hydrate(
            (int) $model->getKey(),
            (int) $model->shipment_id,
            $model->event_code,
            $model->event_status,
            $model->event_description,
            $model->facility,
            $model->city,
            $model->country_iso2,
            $this->toImmutable($model->event_occurred_at) ?? new DateTimeImmutable,
            is_array($model->payload) ? $model->payload : [],
            $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
        );
    }

    private function normalizeDateFilter(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return new DateTimeImmutable($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
