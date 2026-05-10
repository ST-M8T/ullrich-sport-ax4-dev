<?php

declare(strict_types=1);

namespace App\Application\Integrations\Providers;

use App\Domain\Integrations\Contracts\IntegrationProvider;
use App\Domain\Integrations\IntegrationConfigurationSchema;
use App\Domain\Integrations\IntegrationField;
use App\Domain\Integrations\IntegrationType;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Emons Integration Provider
 * SOLID: Single Responsibility - Nur Emons Konfiguration
 * DDD: Domain Service Implementation
 * Beispiel für modulare Erweiterbarkeit
 */
final class EmonsIntegrationProvider implements IntegrationProvider
{
    public function key(): string
    {
        return 'emons';
    }

    public function name(): string
    {
        return 'Emons';
    }

    public function description(): string
    {
        return 'Spedition und Warenwirtschaftssystem für Lagerverwaltung und Bestandsführung.';
    }

    public function type(): IntegrationType
    {
        return IntegrationType::SHIPPING;
    }

    public function configurationSchema(): IntegrationConfigurationSchema
    {
        return IntegrationConfigurationSchema::create([
            new IntegrationField(
                key: 'emons_api_url',
                label: 'API URL',
                type: 'text',
                valueType: 'string',
                required: true,
                placeholder: 'https://api.emons.example.com',
            ),
            new IntegrationField(
                key: 'emons_api_key',
                label: 'API Key',
                type: 'text',
                valueType: 'string',
                required: true,
            ),
            new IntegrationField(
                key: 'emons_api_secret',
                label: 'API Secret',
                type: 'password',
                valueType: 'string',
                required: true,
                secret: true,
            ),
            new IntegrationField(
                key: 'emons_warehouse_id',
                label: 'Lager-ID',
                type: 'text',
                valueType: 'string',
                required: true,
                help: 'Eindeutige Identifikation des Lagers in Emons',
            ),
            new IntegrationField(
                key: 'emons_sync_interval',
                label: 'Sync-Intervall (Minuten)',
                type: 'number',
                valueType: 'int',
                default: 15,
                help: 'Wie oft sollen Bestände synchronisiert werden?',
            ),
            new IntegrationField(
                key: 'emons_auto_sync',
                label: 'Automatische Synchronisation',
                type: 'checkbox',
                valueType: 'bool',
                default: true,
            ),
        ]);
    }

    public function validateConfiguration(array $configuration): bool
    {
        $required = ['emons_api_url', 'emons_api_key', 'emons_api_secret', 'emons_warehouse_id'];

        foreach ($required as $key) {
            if (empty($configuration[$key])) {
                return false;
            }
        }

        return filter_var($configuration['emons_api_url'] ?? '', FILTER_VALIDATE_URL) !== false;
    }

    public function testConnection(array $configuration): bool
    {
        if (! $this->validateConfiguration($configuration)) {
            return false;
        }

        $apiUrl = (string) $configuration['emons_api_url'];

        try {
            // Erreichbarkeitstest gegen die API-Base-URL — keine Auth-Logik im Provider.
            $response = Http::timeout(5)
                ->connectTimeout(5)
                ->head(rtrim($apiUrl, '/'));

            $status = $response->status();

            return $status >= 200 && $status < 500 && $status !== 404;
        } catch (ConnectionException $exception) {
            Log::info('Emons testConnection failed (connection)', [
                'reason' => $exception->getMessage(),
                'api_url' => $apiUrl,
            ]);

            return false;
        } catch (Throwable $exception) {
            Log::warning('Emons testConnection failed (unexpected)', [
                'reason' => $exception->getMessage(),
                'class' => $exception::class,
            ]);

            return false;
        }
    }
}
