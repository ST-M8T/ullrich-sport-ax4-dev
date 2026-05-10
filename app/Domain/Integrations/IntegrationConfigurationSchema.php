<?php

declare(strict_types=1);

namespace App\Domain\Integrations;

/**
 * Integration Configuration Schema
 * Definiert die Struktur der Konfigurationsfelder
 * DDD: Value Object - Immutable Schema Definition
 */
final class IntegrationConfigurationSchema
{
    /**
     * @param  array<int, IntegrationField>  $fields
     */
    private function __construct(
        private readonly array $fields,
    ) {}

    /**
     * @param  array<int, IntegrationField>  $fields
     */
    public static function create(array $fields): self
    {
        return new self($fields);
    }

    /**
     * @return array<int, IntegrationField>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * Konvertiert zu Array für UI
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            fn (IntegrationField $field) => $field->toArray(),
            $this->fields
        );
    }
}
