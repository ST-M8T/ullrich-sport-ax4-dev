<?php

declare(strict_types=1);

namespace App\Application\Configuration\Notifications;

use App\Application\Configuration\NotificationDispatchService;
use App\Application\Configuration\SystemSettingService;
use App\Application\Shared\Casting\BooleanCaster;

/**
 * Projects the channel schema + persisted settings + runtime dispatcher
 * channel-state into view models consumed by the notification settings UI.
 */
final class NotificationChannelViewProjector
{
    public function __construct(
        private readonly NotificationChannelSchema $schema,
        private readonly NotificationDispatchService $dispatchService,
        private readonly SystemSettingService $settings,
    ) {}

    /**
     * Detailed projection: per-channel field metadata for the settings form.
     *
     * @return array<int,array<string,mixed>>
     */
    public function projectChannelSettings(): array
    {
        $channels = $this->dispatchService->channels();
        $result = [];

        foreach ($this->schema->all() as $channelKey => $config) {
            $fields = [];
            foreach ($config['fields'] as $fieldKey => $fieldConfig) {
                $fields[] = $this->projectField($channelKey, $fieldKey, $fieldConfig);
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
     * Compact projection: channel options (key/label/enabled) for selects.
     *
     * @return array<int,array<string,mixed>>
     */
    public function projectChannelOptions(): array
    {
        $channels = $this->dispatchService->channels();
        $options = [];

        foreach ($this->schema->all() as $key => $config) {
            $options[] = [
                'key' => $key,
                'label' => $config['label'],
                'enabled' => isset($channels[$key]) ? $channels[$key]->isEnabled() : false,
            ];
        }

        return $options;
    }

    /**
     * @param array<string,mixed> $fieldConfig
     * @return array<string,mixed>
     */
    private function projectField(string $channelKey, string $fieldKey, array $fieldConfig): array
    {
        $value = $this->resolveFieldValue($fieldConfig);

        return [
            'name' => sprintf('channels[%s][%s]', $channelKey, $fieldKey),
            'id' => sprintf('channel_%s_%s', $channelKey, $fieldKey),
            'errorKey' => sprintf('channels.%s.%s', $channelKey, $fieldKey),
            'label' => $fieldConfig['label'],
            'type' => $fieldConfig['input'],
            'value' => $fieldConfig['value_type'] === 'bool'
                ? BooleanCaster::toBool($value)
                : (string) ($value ?? ''),
            'placeholder' => $fieldConfig['placeholder'] ?? null,
        ];
    }

    /**
     * @param array<string,mixed> $fieldConfig
     */
    private function resolveFieldValue(array $fieldConfig): mixed
    {
        $value = $this->settings->get($fieldConfig['setting_key']);

        if ($value !== null) {
            return $value;
        }

        if (array_key_exists('default', $fieldConfig)) {
            return $fieldConfig['default'];
        }

        if (array_key_exists('default_config', $fieldConfig)) {
            return config($fieldConfig['default_config']);
        }

        return null;
    }
}
