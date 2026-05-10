<?php

declare(strict_types=1);

namespace App\View\Composers\Configuration;

use App\View\Helpers\DomainFormHelper;
use Illuminate\View\View;

/**
 * Configuration Settings Form View Composer
 * Bereitet Settings-Form-Daten vor
 */
final class ConfigurationSettingsFormComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        $setting = $data['setting'] ?? null;
        $isEdit = $setting !== null;
        $action = $data['action'] ?? '';
        $method = $data['method'] ?? 'POST';
        $submitLabel = $data['submitLabel'] ?? 'Speichern';
        $valueTypes = $data['valueTypes'] ?? [];

        $value = fn (string $field, $fallback = null) => DomainFormHelper::value($field, $fallback, $setting);

        $view->with([
            'isEdit' => $isEdit,
            'action' => $action,
            'method' => $method,
            'submitLabel' => $submitLabel,
            'valueTypes' => $valueTypes,
            'value' => $value,
            'settingKeyValue' => $value('key'),
            'valueTypeValue' => $value('valueType'),
            'settingValueValue' => $value('rawValue'),
        ]);
    }
}
