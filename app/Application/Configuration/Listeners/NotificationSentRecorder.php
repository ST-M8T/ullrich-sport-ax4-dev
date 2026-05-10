<?php

declare(strict_types=1);

namespace App\Application\Configuration\Listeners;

use App\Application\Configuration\Events\NotificationSent;
use App\Application\Monitoring\AuditLogger;
use App\Application\Monitoring\DomainEventService;

final class NotificationSentRecorder
{
    public function __construct(
        private readonly DomainEventService $events,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(NotificationSent $event): void
    {
        $message = $event->message;

        $this->events->record(
            'configuration.notification.sent',
            'notification',
            (string) $message->id()->toInt(),
            [
                'channel' => $event->channel,
                'notification_type' => $message->notificationType(),
                'sent_at' => $event->sentAt->format(DATE_ATOM),
                'recipient' => $event->recipient,
            ],
            [
                'template' => $event->template,
            ],
        );

        $this->auditLogger->log(
            'notification.sent',
            'system',
            null,
            null,
            [
                'notification_id' => $message->id()->toInt(),
                'channel' => $event->channel,
                'recipient' => $event->recipient,
            ]
        );
    }
}
