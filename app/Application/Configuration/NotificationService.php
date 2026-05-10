<?php

namespace App\Application\Configuration;

use App\Domain\Configuration\Contracts\NotificationRepository;
use App\Domain\Configuration\NotificationMessage;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;

final class NotificationService
{
    public function __construct(private readonly NotificationRepository $notifications) {}

    /**
     * @psalm-param array<string,mixed> $payload
     */
    public function queue(
        string $type,
        array $payload,
        ?string $channel = null,
        ?DateTimeImmutable $scheduledAt = null
    ): NotificationMessage {
        $id = $this->notifications->nextIdentity();
        $now = new DateTimeImmutable;

        $message = NotificationMessage::hydrate(
            $id,
            $type,
            $channel,
            $payload,
            'pending',
            $scheduledAt,
            null,
            null,
            $now,
            $now,
        );

        $this->notifications->save($message);

        return $message;
    }

    public function markSent(Identifier $id): void
    {
        $message = $this->notifications->getById($id);
        if (! $message) {
            throw new \RuntimeException('Notification not found.');
        }

        $now = new DateTimeImmutable;
        $sent = NotificationMessage::hydrate(
            $message->id(),
            $message->notificationType(),
            $message->channel(),
            $message->payload(),
            'sent',
            $message->scheduledAt(),
            $now,
            null,
            $message->createdAt(),
            $now,
        );

        $this->notifications->save($sent);
    }
}
