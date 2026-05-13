<?php

declare(strict_types=1);

namespace App\Application\Configuration\SystemSettings;

/**
 * Builds Laravel-Validation-Rules for a system-settings group schema.
 *
 * Reine Mapping-Logik: input-type ($field['type']) -> validation rules.
 * Hat keine Laravel-Request-Abhängigkeit, ist deshalb unabhängig testbar.
 */
final class SystemSettingGroupRulesBuilder
{
    /**
     * @param  array<int,array<string,mixed>>  $fields
     * @return array<string,array<int,string>>
     */
    public function build(array $fields): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $key = $field['key'];
            $type = $field['type'] ?? 'text';

            $rules[$key] = match ($type) {
                'number' => ['nullable', 'numeric'],
                'checkbox' => ['nullable', 'boolean'],
                'email' => ['nullable', 'email'],
                'select' => ['nullable', 'string', 'max:255'],
                'password', 'text', 'textarea' => ['nullable', 'string'],
                default => ['nullable', 'string'],
            };
        }

        return $rules;
    }
}
