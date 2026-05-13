<?php

declare(strict_types=1);

namespace App\Application\Configuration\Notifications;

/**
 * Single source of truth for notification channel configuration metadata.
 *
 * Defines the channels (mail, slack, sms), their fields, input types,
 * setting keys, value types, validation rules and defaults.
 */
final class NotificationChannelSchema
{
    /**
     * @var array<string,array<string,mixed>>
     */
    private const CHANNELS = [
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

    /**
     * @return array<string,array<string,mixed>>
     */
    public function all(): array
    {
        return self::CHANNELS;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(self::CHANNELS);
    }
}
