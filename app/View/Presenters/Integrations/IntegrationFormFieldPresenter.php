<?php

declare(strict_types=1);

namespace App\View\Presenters\Integrations;

use App\Domain\Integrations\IntegrationField;

/**
 * Integration Form Field Service
 * Bereitet Formular-Feld-Daten für Integrations-Views vor
 * SOLID: Single Responsibility - Nur Feld-Verarbeitung
 * DDD: Application Service - Orchestriert Integrations-Form-Logik
 */
final class IntegrationFormFieldPresenter
{
    /**
     * Verarbeitet ein Integrations-Feld für die Anzeige
     *
     * @param  mixed  $currentValue
     * @return array<string, mixed>
     */
    public function processField(
        IntegrationField $field,
        string $fieldKey,
        $currentValue,
        bool $isSecret
    ): array {
        $type = $field->type();
        $label = $field->label();
        $isRequired = $field->isRequired();
        $placeholder = $field->placeholder();

        $processedValue = $currentValue;
        if ($isSecret && $currentValue === '***SET***') {
            $processedValue = '';
        }

        return [
            'type' => $type,
            'fieldKey' => $fieldKey,
            'label' => $label,
            'isRequired' => $isRequired,
            'placeholder' => $placeholder,
            'currentValue' => $currentValue,
            'processedValue' => $processedValue,
            'isSecret' => $isSecret,
            'options' => $field->options() ?? [],
        ];
    }

    /**
     * Bestimmt, welcher Komponenten-Name für einen Feld-Typ verwendet werden soll
     */
    public function getComponentNameForType(string $type): string
    {
        return match ($type) {
            'select' => 'forms.select',
            'textarea' => 'forms.textarea',
            'checkbox' => 'forms.checkbox',
            'number' => 'forms.input',
            'email' => 'forms.input',
            'password' => 'forms.input',
            default => 'forms.input',
        };
    }
}
