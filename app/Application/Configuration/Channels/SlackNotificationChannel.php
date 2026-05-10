<?php

namespace App\Application\Configuration\Channels;

use App\Application\Configuration\SystemSettingService;
use App\Domain\Configuration\NotificationMessage;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

final class SlackNotificationChannel implements NotificationChannel
{
    private const ENABLED_KEY = 'notifications.slack.enabled';

    private const WEBHOOK_KEY = 'notifications.slack.webhook_url';

    private const DEFAULT_CHANNEL_KEY = 'notifications.slack.channel';

    public function __construct(
        private readonly SystemSettingService $settings,
        private readonly HttpFactory $http,
    ) {}

    public function key(): string
    {
        return 'slack';
    }

    public function isEnabled(): bool
    {
        return $this->toBool($this->settings->get(self::ENABLED_KEY))
            && ! empty($this->settings->get(self::WEBHOOK_KEY));
    }

    public function send(NotificationMessage $message): bool
    {
        $payload = $message->payload();
        $text = trim((string) ($payload['text'] ?? $payload['message'] ?? ''));

        if ($text === '') {
            return false;
        }

        $webhook = $payload['webhook_url'] ?? $this->settings->get(self::WEBHOOK_KEY);
        if (empty($webhook)) {
            return false;
        }

        $channel = $payload['channel'] ?? $this->settings->get(self::DEFAULT_CHANNEL_KEY);
        $requestBody = ['text' => $text];
        if (! empty($channel)) {
            $requestBody['channel'] = $channel;
        }

        /** @var Response $response */
        $response = $this->http->post($webhook, $requestBody);

        return $response->successful();
    }

    private function toBool(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
}
