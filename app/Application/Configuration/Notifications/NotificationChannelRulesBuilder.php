<?php

declare(strict_types=1);

namespace App\Application\Configuration\Notifications;

/**
 * Builds Laravel validation rules for the channel settings form
 * based on the {@see NotificationChannelSchema}.
 */
final class NotificationChannelRulesBuilder
{
    public function __construct(private readonly NotificationChannelSchema $schema) {}

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        $rules = ['channels' => ['required', 'array']];

        foreach ($this->schema->all() as $channelKey => $config) {
            foreach ($config['fields'] as $fieldKey => $fieldConfig) {
                $rules[sprintf('channels.%s.%s', $channelKey, $fieldKey)] = $fieldConfig['rules'];
            }
        }

        return $rules;
    }
}
