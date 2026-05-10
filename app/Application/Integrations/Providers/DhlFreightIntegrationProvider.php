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
 * DHL Freight Integration Provider
 * SOLID: Single Responsibility - Nur DHL Freight Konfiguration
 * DDD: Domain Service Implementation
 */
final class DhlFreightIntegrationProvider implements IntegrationProvider
{
    public function key(): string
    {
        return 'dhl_freight';
    }

    public function name(): string
    {
        return 'DHL Freight';
    }

    public function description(): string
    {
        return 'REST-Endpunkte für Buchung, Preise und Label.';
    }

    public function type(): IntegrationType
    {
        return IntegrationType::SHIPPING;
    }

    public function configurationSchema(): IntegrationConfigurationSchema
    {
        return IntegrationConfigurationSchema::create([
            new IntegrationField(
                key: 'dhl_freight_base_url',
                label: 'Base URL',
                type: 'text',
                valueType: 'string',
                required: true,
                placeholder: 'https://api-sandbox.dhl.com/freight',
                default: 'https://api-sandbox.dhl.com/freight',
            ),
            new IntegrationField(
                key: 'dhl_freight_api_key',
                label: 'API Key / Token',
                type: 'text',
                valueType: 'string',
                required: true,
            ),
            new IntegrationField(
                key: 'dhl_freight_api_secret',
                label: 'API Secret',
                type: 'password',
                valueType: 'string',
                required: true,
                secret: true,
            ),
            new IntegrationField(
                key: 'dhl_freight_auth',
                label: 'Auth-Modus',
                type: 'select',
                valueType: 'string',
                required: true,
                options: [
                    'bearer' => 'Bearer (Empfohlen)',
                    'basic' => 'Basic Auth',
                    'header' => 'Custom Header',
                ],
                default: 'bearer',
            ),
            new IntegrationField(
                key: 'dhl_freight_timeout',
                label: 'Timeout (Sek.)',
                type: 'number',
                valueType: 'int',
                default: 10,
            ),
            new IntegrationField(
                key: 'dhl_freight_connect_timeout',
                label: 'Connect Timeout (Sek.)',
                type: 'number',
                valueType: 'int',
                default: 5,
            ),
            new IntegrationField(
                key: 'dhl_freight_verify_ssl',
                label: 'SSL-Zertifikate prüfen',
                type: 'checkbox',
                valueType: 'bool',
                default: true,
            ),
        ]);
    }

    public function validateConfiguration(array $configuration): bool
    {
        $required = ['dhl_freight_base_url', 'dhl_freight_api_key', 'dhl_freight_api_secret', 'dhl_freight_auth'];

        foreach ($required as $key) {
            if (empty($configuration[$key])) {
                return false;
            }
        }

        return true;
    }

    public function testConnection(array $configuration): bool
    {
        if (! $this->validateConfiguration($configuration)) {
            return false;
        }

        $baseUrl = (string) $configuration['dhl_freight_base_url'];
        $timeoutSeconds = max(1, (int) ($configuration['dhl_freight_connect_timeout'] ?? 5));
        $verifySsl = (bool) ($configuration['dhl_freight_verify_ssl'] ?? true);

        try {
            // HEAD-Request gegen die Base-URL — prüft Erreichbarkeit ohne Auth-Endpunkt zu treffen.
            // Akzeptierte Erfolgs-Codes: 200..499 ohne 404 (Server reagiert).
            $response = Http::timeout($timeoutSeconds)
                ->connectTimeout($timeoutSeconds)
                ->withOptions(['verify' => $verifySsl])
                ->head(rtrim($baseUrl, '/'));

            $status = $response->status();

            return $status >= 200 && $status < 500 && $status !== 404;
        } catch (ConnectionException $exception) {
            Log::info('DHL Freight testConnection failed (connection)', [
                'reason' => $exception->getMessage(),
                'base_url' => $baseUrl,
            ]);

            return false;
        } catch (Throwable $exception) {
            Log::warning('DHL Freight testConnection failed (unexpected)', [
                'reason' => $exception->getMessage(),
                'class' => $exception::class,
            ]);

            return false;
        }
    }
}
