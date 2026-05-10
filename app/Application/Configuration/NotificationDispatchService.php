<?php

namespace App\Application\Configuration;

use App\Application\Configuration\Channels\NotificationChannel;
use App\Application\Configuration\Events\NotificationSent;
use App\Domain\Configuration\Contracts\NotificationRepository;
use App\Domain\Configuration\NotificationMessage;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use Illuminate\Support\Facades\Event;

final class NotificationDispatchService
{
    /** @var array<string,NotificationChannel> */
    private array $channels;

    /**
     * @param  iterable<NotificationChannel>  $channels
     */
    public function __construct(
        private readonly NotificationRepository $notifications,
        iterable $channels,
    ) {
        $this->channels = [];
        foreach ($channels as $channel) {
            $this->channels[$channel->key()] = $channel;
        }
    }

    /**
     * @param  callable(NotificationMessage,bool):void|null  $progress
     */
    public function dispatchPending(int $limit = 50, ?callable $progress = null): int
    {
        $pending = $this->notifications->search(['status' => 'pending'], $limit);
        $count = 0;
        foreach ($pending as $message) {
            $sent = $this->sendNotification($message);
            if ($sent) {
                $count++;
            }

            if ($progress) {
                $progress($message, $sent);
            }
        }

        return $count;
    }

    public function dispatchSingle(Identifier $id, bool $resetStatus = true): bool
    {
        $message = $this->notifications->getById($id);
        if (! $message) {
            return false;
        }

        if ($resetStatus && ! $message->isPending()) {
            $message = NotificationMessage::hydrate(
                $message->id(),
                $message->notificationType(),
                $message->channel(),
                $message->payload(),
                'pending',
                $message->scheduledAt(),
                null,
                null,
                $message->createdAt(),
                new DateTimeImmutable,
            );

            $this->notifications->save($message);
        }

        return $this->sendNotification($message);
    }

    private function sendNotification(NotificationMessage $message): bool
    {
        $channelKey = $message->channel() ?: 'mail';
        $channel = $this->channels[$channelKey] ?? null;

        if (! $channel || ! $channel->isEnabled()) {
            return false;
        }

        if (! $channel->send($message)) {
            return false;
        }

        $sentAt = new DateTimeImmutable;
        $updated = NotificationMessage::hydrate(
            $message->id(),
            $message->notificationType(),
            $message->channel(),
            $message->payload(),
            'sent',
            $message->scheduledAt(),
            $sentAt,
            null,
            $message->createdAt(),
            new DateTimeImmutable,
        );

        $this->notifications->save($updated);

        $payload = $message->payload();
        $recipient = $this->resolveRecipient($payload);
        $templateKey = $payload['template'] ?? null;

        Event::dispatch(new NotificationSent(
            $updated,
            $message->channel() ?? $channelKey,
            $sentAt,
            $recipient,
            is_string($templateKey ?? null) ? $templateKey : null,
        ));

        return true;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function resolveRecipient(array $payload): ?string
    {
        $candidates = [
            'to',
            'recipient',
            'phone',
            'channel',
            'webhook_url',
        ];

        foreach ($candidates as $key) {
            if (! empty($payload[$key]) && is_scalar($payload[$key])) {
                return (string) $payload[$key];
            }
        }

        return null;
    }

    /**
     * @return array<string,NotificationChannel>
     */
    public function channels(): array
    {
        return $this->channels;
    }
}
