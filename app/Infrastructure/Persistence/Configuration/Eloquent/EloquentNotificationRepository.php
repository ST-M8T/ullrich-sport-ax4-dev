<?php

namespace App\Infrastructure\Persistence\Configuration\Eloquent;

use App\Domain\Configuration\Contracts\NotificationRepository;
use App\Domain\Configuration\NotificationMessage;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Support\Persistence\CastsDateTime;
use DateTimeImmutable;

final class EloquentNotificationRepository implements NotificationRepository
{
    use CastsDateTime;

    public function nextIdentity(): Identifier
    {
        $next = (int) (NotificationModel::query()->max('id') ?? 0) + 1;

        return Identifier::fromInt($next);
    }

    public function save(NotificationMessage $notification): void
    {
        $model = NotificationModel::find($notification->id()->toInt()) ?? new NotificationModel(['id' => $notification->id()->toInt()]);

        $model->notification_type = $notification->notificationType();
        $model->channel = $notification->channel();
        $model->payload = $notification->payload();
        $model->status = $notification->status();
        $model->scheduled_at = $notification->scheduledAt();
        $model->sent_at = $notification->sentAt();
        $model->error_message = $notification->errorMessage();
        $model->save();
    }

    public function getById(Identifier $id): ?NotificationMessage
    {
        $model = NotificationModel::find($id->toInt());

        return $model ? $this->mapModel($model) : null;
    }

    public function search(array $filters = [], int $limit = 100, int $offset = 0): iterable
    {
        $query = NotificationModel::query()
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['notification_type']), fn ($q) => $q->where('notification_type', $filters['notification_type']))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->offset($offset);

        return $query->get()->map(fn (NotificationModel $model) => $this->mapModel($model));
    }

    private function mapModel(NotificationModel $model): NotificationMessage
    {
        return NotificationMessage::hydrate(
            Identifier::fromInt((int) $model->getKey()),
            $model->notification_type,
            $model->channel,
            is_array($model->payload) ? $model->payload : [],
            $model->status,
            $this->toImmutable($model->scheduled_at),
            $this->toImmutable($model->sent_at),
            $model->error_message,
            $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
            $this->toImmutable($model->updated_at) ?? new DateTimeImmutable,
        );
    }
}
