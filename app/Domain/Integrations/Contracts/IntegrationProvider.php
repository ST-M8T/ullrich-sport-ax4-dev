<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Contracts;

use App\Domain\Integrations\IntegrationConfigurationSchema;

/**
 * Integration Provider Interface
 * Plugin-System für neue Integrationen
 * SOLID: Interface Segregation - Jede Integration implementiert nur was sie braucht
 * DDD: Domain Service Interface
 */
interface IntegrationProvider
{
    /**
     * Eindeutiger Schlüssel der Integration
     */
    public function key(): string;

    /**
     * Anzeigename der Integration
     */
    public function name(): string;

    /**
     * Beschreibung der Integration
     */
    public function description(): string;

    /**
     * Typ der Integration
     */
    public function type(): \App\Domain\Integrations\IntegrationType;

    /**
     * Konfigurationsschema für die UI
     * Definiert welche Felder in der Settings-UI angezeigt werden
     */
    public function configurationSchema(): IntegrationConfigurationSchema;

    /**
     * Validiert die Konfiguration
     *
     * @param  array<string, mixed>  $configuration
     */
    public function validateConfiguration(array $configuration): bool;

    /**
     * Testet die Verbindung zur Integration
     *
     * @param  array<string, mixed>  $configuration
     */
    public function testConnection(array $configuration): bool;
}
