<?php

namespace App\Application\Configuration\Channels;

use App\Application\Configuration\SystemSettingService;
use App\Domain\Configuration\NotificationMessage;

final class SmsNotificationChannel implements NotificationChannel
{
    private const ENABLED_KEY = 'notifications.sms.enabled';

    private const SENDER_KEY = 'notifications.sms.sender';

    public function __construct(
        private readonly SystemSettingService $settings,
        private readonly SmsSender $sender,
    ) {}

    public function key(): string
    {
        return 'sms';
    }

    public function isEnabled(): bool
    {
        return $this->toBool($this->settings->get(self::ENABLED_KEY));
    }

    public function send(NotificationMessage $message): bool
    {
        $payload = $message->payload();
        $recipient = trim((string) ($payload['to'] ?? $payload['phone'] ?? ''));
        $text = trim((string) ($payload['text'] ?? $payload['message'] ?? ''));

        if ($recipient === '' || $text === '') {
            return false;
        }

        $sender = $payload['sender'] ?? $this->settings->get(self::SENDER_KEY);
        $context = array_filter([
            'sender' => $sender,
            'notification_type' => $message->notificationType(),
        ], static fn ($value) => $value !== null && $value !== '');

        return $this->sender->send($recipient, $text, $context);
    }

    private function toBool(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
}
