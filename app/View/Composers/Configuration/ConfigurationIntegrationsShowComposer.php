<?php

declare(strict_types=1);

namespace App\View\Composers\Configuration;

use App\View\Presenters\Integrations\IntegrationFormFieldPresenter;
use Illuminate\View\View;

/**
 * Configuration Integrations Show View Composer
 * Bereitet Integrations-Show-Daten vor
 */
final class ConfigurationIntegrationsShowComposer
{
    public function __construct(
        private readonly IntegrationFormFieldPresenter $integrationFormFieldService
    ) {}

    public function compose(View $view): void
    {
        $data = $view->getData();

        $schema = $data['schema'] ?? null;
        $configuration = $data['configuration'] ?? [];

        if ($schema === null) {
            return;
        }

        $processedFields = [];
        foreach ($schema->fields() as $field) {
            $fieldKey = $field->key();
            $currentValue = $configuration[$fieldKey] ?? $field->default();
            $isSecret = $field->isSecret();

            $processedField = $this->integrationFormFieldService->processField(
                $field,
                $fieldKey,
                $currentValue,
                $isSecret
            );

            $processedField['componentName'] = $this->integrationFormFieldService->getComponentNameForType(
                $processedField['type']
            );
            $processedField['field'] = $field;
            $processedField['help'] = $field->help();
            $processedField['showSecretHint'] = $isSecret && ($currentValue === '***SET***' || $currentValue);
            $processedField['inputType'] = $field->type() === 'password' ? 'password' : 'text';
            $processedField['isChecked'] = $currentValue == '1' || $currentValue === true || $currentValue === 1;

            $processedFields[$fieldKey] = $processedField;
        }

        $view->with([
            'processedFields' => $processedFields,
        ]);
    }
}
