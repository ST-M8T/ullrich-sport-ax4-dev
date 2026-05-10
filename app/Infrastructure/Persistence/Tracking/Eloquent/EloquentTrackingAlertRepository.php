<?php

namespace App\Infrastructure\Persistence\Tracking\Eloquent;

use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Tracking\Contracts\TrackingAlertRepository;
use App\Domain\Tracking\TrackingAlert;
use App\Support\Persistence\CastsDateTime;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentTrackingAlertRepository implements TrackingAlertRepository
{
    use CastsDateTime;

    public function nextIdentity(): Identifier
    {
        return DB::transaction(function (): Identifier {
            $next = (int) (TrackingAlertModel::query()->lockForUpdate()->max('id') ?? 0) + 1;

            return Identifier::fromInt($next);
        }, 5);
    }

    public function find(array $filters = []): iterable
    {
        $query = TrackingAlertModel::query()
            ->when(isset($filters['alert_type']), fn ($q) => $q->where('alert_type', $filters['alert_type']))
            ->when(isset($filters['severity']), fn ($q) => $q->where('severity', $filters['severity']))
            ->when(isset($filters['channel']), fn ($q) => $q->where('channel', $filters['channel']))
            ->when(isset($filters['is_acknowledged']), function ($q) use ($filters) {
                return $filters['is_acknowledged']
                    ? $q->whereNotNull('acknowledged_at')
                    : $q->whereNull('acknowledged_at');
            })
            ->orderByDesc('created_at');

        return $query->get()->map(fn (TrackingAlertModel $model) => $this->mapModel($model));
    }

    public function getById(Identifier $id): ?TrackingAlert
    {
        $model = TrackingAlertModel::find($id->toInt());

        return $model ? $this->mapModel($model) : null;
    }

    public function save(TrackingAlert $alert): void
    {
        $model = TrackingAlertModel::find($alert->id()->toInt()) ?? new TrackingAlertModel(['id' => $alert->id()->toInt()]);

        $model->shipment_id = $alert->shipmentId()?->toInt();
        $model->alert_type = $alert->alertType();
        $model->severity = $alert->severity();
        $model->channel = $alert->channel();
        $model->message = $alert->message();
        $model->sent_at = $alert->sentAt();
        $model->acknowledged_at = $alert->acknowledgedAt();
        $model->metadata = $alert->metadata();
        $model->save();
    }

    private function mapModel(TrackingAlertModel $model): TrackingAlert
    {
        return TrackingAlert::hydrate(
            Identifier::fromInt((int) $model->getKey()),
            $model->shipment_id !== null ? Identifier::fromInt((int) $model->shipment_id) : null,
            $model->alert_type,
            $model->severity,
            $model->channel,
            $model->message,
            $this->toImmutable($model->sent_at),
            $this->toImmutable($model->acknowledged_at),
            is_array($model->metadata) ? $model->metadata : [],
            $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
            $this->toImmutable($model->updated_at) ?? new DateTimeImmutable,
        );
    }
}
