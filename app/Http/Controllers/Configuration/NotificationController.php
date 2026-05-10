<?php

namespace App\Http\Controllers\Configuration;

use App\Application\Configuration\NotificationDispatchService;
use App\Application\Configuration\NotificationService;
use App\Application\Configuration\Queries\ListNotifications;
use App\Application\Configuration\SystemSettingService;
use App\Domain\Shared\ValueObjects\Identifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use JsonException;

final class NotificationController
{
    private const CHANNEL_SETTINGS = [
        'mail' => [
            'label' => 'E-Mail',
            'fields' => [
                'enabled' => [
                    'label' => 'Aktiv',
                    'input' => 'checkbox',
                    'setting_key' => 'notifications.mail.enabled',
                    'value_type' => 'bool',
                    'default' => '1',
                    'rules' => ['nullable', 'boolean'],
                ],
                'from_email' => [
                    'label' => 'Absender-Adresse',
                    'input' => 'email',
                    'setting_key' => 'mail_from_email',
                    'value_type' => 'string',
                    'rules' => ['nullable', 'email', 'max:255'],
                    'default_config' => 'mail.from.address',
                ],
                'from_name' => [
                    'label' => 'Absender-Name',
                    'input' => 'text',
                    'setting_key' => 'mail_from_name',
                    'value_type' => 'string',
                    'rules' => ['nullable', 'string', 'max:255'],
                    'default_config' => 'mail.from.name',
                ],
            ],
        ],
        'slack' => [
            'label' => 'Slack',
            'fields' => [
                'enabled' => [
                    'label' => 'Aktiv',
                    'input' => 'checkbox',
                    'setting_key' => 'notifications.slack.enabled',
                    'value_type' => 'bool',
                    'default' => '0',
                    'rules' => ['nullable', 'boolean'],
                ],
                'webhook_url' => [
                    'label' => 'Webhook URL',
                    'input' => 'url',
                    'setting_key' => 'notifications.slack.webhook_url',
                    'value_type' => 'secret',
                    'rules' => ['nullable', 'url', 'max:255'],
                    'placeholder' => 'https://hooks.slack.com/services/...',
                ],
                'channel' => [
                    'label' => 'Standard-Channel',
                    'input' => 'text',
                    'setting_key' => 'notifications.slack.channel',
                    'value_type' => 'string',
                    'rules' => ['nullable', 'string', 'max:80'],
                    'placeholder' => '#alerts',
                ],
            ],
        ],
        'sms' => [
            'label' => 'SMS',
            'fields' => [
                'enabled' => [
                    'label' => 'Aktiv',
                    'input' => 'checkbox',
                    'setting_key' => 'notifications.sms.enabled',
                    'value_type' => 'bool',
                    'default' => '0',
                    'rules' => ['nullable', 'boolean'],
                ],
                'sender' => [
                    'label' => 'Absender-Kennung',
                    'input' => 'text',
                    'setting_key' => 'notifications.sms.sender',
                    'value_type' => 'string',
                    'rules' => ['nullable', 'string', 'max:32'],
                    'placeholder' => 'Logistics',
                ],
            ],
        ],
    ];

    public function __construct(
        private readonly ListNotifications $listNotifications,
        private readonly NotificationDispatchService $dispatchService,
        private readonly NotificationService $notificationService,
        private readonly SystemSettingService $settings,
        private readonly Redirector $redirector,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'notification_type' => $request->string('notification_type')->trim(),
            'status' => $request->string('status')->trim(),
        ];

        $limit = max(1, min(200, (int) $request->integer('limit', 100)));
        $offset = max(0, (int) $request->integer('offset', 0));

        $notifications = ($this->listNotifications)($filters, $limit, $offset);

        return view('configuration.notifications.index', [
            'notifications' => $notifications,
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
            'channelSettings' => $this->buildChannelSettings(),
            'availableChannels' => $this->channelOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'notification_type' => ['required', 'string', 'max:255'],
            'channel' => [
                'nullable',
                'string',
                'max:32',
                Rule::in(array_merge(array_keys(self::CHANNEL_SETTINGS), [''])),
            ],
            'template_key' => ['nullable', 'string', 'max:255'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'payload' => ['nullable', 'string'],
            'schedule_at' => ['nullable', 'date'],
        ]);

        $payload = [];

        if (! empty($data['payload'])) {
            try {
                $payload = json_decode($data['payload'], true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return $this->redirector
                    ->route('configuration-settings', ['tab' => 'verwaltung', 'verwaltung_tab' => 'notifications'])
                    ->withInput()
                    ->with('error', 'Payload muss gültiges JSON sein.');
            }

            if (! is_array($payload)) {
                return $this->redirector
                    ->route('configuration-settings', ['tab' => 'verwaltung', 'verwaltung_tab' => 'notifications'])
                    ->withInput()
                    ->with('error', 'Payload muss ein JSON-Objekt sein.');
            }
        }

        if (! empty($data['template_key']) && ! array_key_exists('template', $payload)) {
            $payload['template'] = $data['template_key'];
        }

        if (! empty($data['recipient']) && ! array_key_exists('to', $payload)) {
            $payload['to'] = $data['recipient'];
        }

        $scheduledAt = null;
        if (! empty($data['schedule_at'])) {
            $scheduledAt = new \DateTimeImmutable($data['schedule_at']);
        }

        $channel = $data['channel'] ?? null;
        if ($channel === '') {
            $channel = null;
        }

        $message = $this->notificationService->queue(
            $data['notification_type'],
            $payload,
            $channel,
            $scheduledAt
        );

        return $this->redirector
            ->route('configuration-settings', ['tab' => 'verwaltung', 'verwaltung_tab' => 'notifications'])
            ->with('success', sprintf('Benachrichtigung #%d wurde angelegt.', $message->id()->toInt()));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $rules = $this->channelValidationRules();
        $data = $request->validate($rules);

        /** @var array<string,array<string,mixed>> $channels */
        $channels = $data['channels'] ?? [];

        foreach (self::CHANNEL_SETTINGS as $channelKey => $config) {
            $channelValues = $channels[$channelKey] ?? [];

            foreach ($config['fields'] as $fieldKey => $fieldConfig) {
                $value = $channelValues[$fieldKey] ?? null;
                $settingKey = $fieldConfig['setting_key'];
                $valueType = $fieldConfig['value_type'];

                if ($valueType === 'bool') {
                    $value = $this->toBool($value) ? '1' : '0';
                } else {
                    $value = isset($value) && $value !== '' ? (string) $value : null;
                }

                $this->settings->set(
                    $settingKey,
                    $value,
                    $valueType,
                    ((int) Auth::id()) ?: null
                );
            }
        }

        return $this->redirector
            ->route('configuration-settings', ['tab' => 'settings', 'settings_group' => 'notifications'])
            ->with('success', 'Channel-Einstellungen gespeichert.');
    }

    public function dispatch(Request $request): RedirectResponse
    {
        $limit = max(1, min(200, (int) $request->integer('limit', 50)));
        $count = $this->dispatchService->dispatchPending($limit);

        return $this->redirector
            ->route('configuration-settings', ['tab' => 'verwaltung', 'verwaltung_tab' => 'notifications'])
            ->with('success', sprintf('%d Benachrichtigungen ausgeliefert.', $count));
    }

    public function redispatch(int $notification): RedirectResponse
    {
        try {
            $id = Identifier::fromInt($notification);
        } catch (\InvalidArgumentException) {
            return $this->redirector
                ->route('configuration-settings', ['tab' => 'verwaltung', 'verwaltung_tab' => 'notifications'])
                ->with('error', 'Ungültige Benachrichtigungs-ID.');
        }

        $dispatched = $this->dispatchService->dispatchSingle($id);

        if (! $dispatched) {
            return $this->redirector
                ->route('configuration-settings', ['tab' => 'verwaltung', 'verwaltung_tab' => 'notifications'])
                ->with('error', sprintf('Benachrichtigung #%d konnte nicht gesendet werden.', $id->toInt()));
        }

        return $this->redirector
            ->route('configuration-settings', ['tab' => 'verwaltung', 'verwaltung_tab' => 'notifications'])
            ->with('success', sprintf('Benachrichtigung #%d wurde erneut gesendet.', $id->toInt()));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildChannelSettings(): array
    {
        $channels = $this->dispatchService->channels();
        $result = [];

        foreach (self::CHANNEL_SETTINGS as $channelKey => $config) {
            $fields = [];
            foreach ($config['fields'] as $fieldKey => $fieldConfig) {
                $settingKey = $fieldConfig['setting_key'];
                $value = $this->settings->get($settingKey);

                if ($value === null) {
                    if (array_key_exists('default', $fieldConfig)) {
                        $value = $fieldConfig['default'];
                    } elseif (array_key_exists('default_config', $fieldConfig)) {
                        $value = config($fieldConfig['default_config']);
                    }
                }

                $fields[] = [
                    'name' => sprintf('channels[%s][%s]', $channelKey, $fieldKey),
                    'id' => sprintf('channel_%s_%s', $channelKey, $fieldKey),
                    'errorKey' => sprintf('channels.%s.%s', $channelKey, $fieldKey),
                    'label' => $fieldConfig['label'],
                    'type' => $fieldConfig['input'],
                    'value' => $fieldConfig['value_type'] === 'bool'
                        ? $this->toBool($value)
                        : (string) ($value ?? ''),
                    'placeholder' => $fieldConfig['placeholder'] ?? null,
                ];
            }

            $result[] = [
                'key' => $channelKey,
                'label' => $config['label'],
                'fields' => $fields,
                'enabled' => isset($channels[$channelKey]) ? $channels[$channelKey]->isEnabled() : false,
            ];
        }

        return $result;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function channelOptions(): array
    {
        $channels = $this->dispatchService->channels();
        $options = [];

        foreach (self::CHANNEL_SETTINGS as $key => $config) {
            $options[] = [
                'key' => $key,
                'label' => $config['label'],
                'enabled' => isset($channels[$key]) ? $channels[$key]->isEnabled() : false,
            ];
        }

        return $options;
    }

    /**
     * @return array<string,mixed>
     */
    private function channelValidationRules(): array
    {
        $rules = ['channels' => ['required', 'array']];

        foreach (self::CHANNEL_SETTINGS as $channelKey => $config) {
            foreach ($config['fields'] as $fieldKey => $fieldConfig) {
                $rules[sprintf('channels.%s.%s', $channelKey, $fieldKey)] = $fieldConfig['rules'];
            }
        }

        return $rules;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }
}
