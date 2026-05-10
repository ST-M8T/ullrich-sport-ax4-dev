<?php

declare(strict_types=1);

namespace App\View\Composers\Configuration;

use Illuminate\View\View;

/**
 * Configuration Settings Settings View Composer
 * Bereitet Settings-Partial-Daten vor
 */
final class ConfigurationSettingsSettingsComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        /** @var array<string, array<string, mixed>> $groups */
        $groups = $data['groups'] ?? [];
        $settings = $data['settings'] ?? null;

        $groupTabs = collect($groups)->mapWithKeys(function ($group, $slug) {
            return [$slug => ['label' => $group['label'] ?? $slug]];
        })->all();

        $processedGroups = [];
        foreach ($groups as $slug => $group) {
            $processedFields = [];
            foreach (($group['fields'] ?? []) as $field) {
                $fieldKey = $field['key'];
                $entry = $settings?->get($fieldKey);
                $isSecret = ($field['value_type'] ?? '') === 'secret';
                $type = $field['type'] ?? 'text';
                $default = $field['default'] ?? null;

                if ($type === 'checkbox') {
                    $checkboxDefault = $entry ? ($entry->rawValue() === '1' || $entry->rawValue() === 'true') : (bool) $default;
                    // old() erwartet string|null als Default — wir kodieren bool zu '0'/'1'.
                    $current = (bool) old($fieldKey, $checkboxDefault ? '1' : '0');
                } elseif ($isSecret) {
                    $current = old($fieldKey, '');
                } else {
                    $current = old($fieldKey, $entry?->rawValue() ?? $default);
                }

                $processedFields[] = array_merge($field, [
                    'fieldKey' => $fieldKey,
                    'entry' => $entry,
                    'isSecret' => $isSecret,
                    'type' => $type,
                    'current' => $current,
                ]);
            }

            $processedGroups[$slug] = array_merge($group, [
                'fields' => $processedFields,
            ]);
        }

        $view->with([
            'groupTabs' => $groupTabs,
            'processedGroups' => $processedGroups,
            'mailTemplates' => $data['mailTemplates'] ?? [],
        ]);
    }
}
