<?php

declare(strict_types=1);

namespace App\Application\Configuration\Notifications;

use App\Application\Configuration\SystemSettingService;
use App\Application\Shared\Casting\BooleanCaster;

/**
 * Application service: persists validated channel settings into the
 * SystemSettingService, applying the value-type coercion defined in
 * the channel schema.
 */
final class NotificationChannelSettingsUpdater
{
    public function __construct(
        private readonly NotificationChannelSchema $schema,
        private readonly SystemSettingService $settings,
    ) {}

    /**
     * @param array<string,array<string,mixed>> $channels validated input (channels.<key>.<field>)
     */
    public function update(array $channels, ?int $actorId): void
    {
        foreach ($this->schema->all() as $channelKey => $config) {
            $channelValues = $channels[$channelKey] ?? [];

            foreach ($config['fields'] as $fieldKey => $fieldConfig) {
                $this->persistField(
                    $fieldConfig,
                    $channelValues[$fieldKey] ?? null,
                    $actorId,
                );
            }
        }
    }

    /**
     * @param array<string,mixed> $fieldConfig
     */
    private function persistField(array $fieldConfig, mixed $value, ?int $actorId): void
    {
        $valueType = $fieldConfig['value_type'];

        if ($valueType === 'bool') {
            $stored = BooleanCaster::toBool($value) ? '1' : '0';
        } else {
            $stored = isset($value) && $value !== '' ? (string) $value : null;
        }

        $this->settings->set(
            $fieldConfig['setting_key'],
            $stored,
            $valueType,
            $actorId,
        );
    }
}
