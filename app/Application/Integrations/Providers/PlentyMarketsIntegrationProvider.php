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
 * PlentyMarkets Integration Provider
 * SOLID: Single Responsibility - Nur PlentyMarkets Konfiguration
 * DDD: Domain Service Implementation
 */
final class PlentyMarketsIntegrationProvider implements IntegrationProvider
{
    public function key(): string
    {
        return 'plentymarkets';
    }

    public function name(): string
    {
        return 'PlentyMarkets';
    }

    public function description(): string
    {
        return 'E-Commerce-Plattform für Bestellungen, Artikel und Lagerbestände.';
    }

    public function type(): IntegrationType
    {
        return IntegrationType::ECOMMERCE;
    }

    public function configurationSchema(): IntegrationConfigurationSchema
    {
        return IntegrationConfigurationSchema::create([
            new IntegrationField(
                key: 'plenty_base_url',
                label: 'Base URL',
                type: 'text',
                valueType: 'string',
                required: true,
                placeholder: 'https://example.plentymarkets-cloud01.com',
                help: 'Vollständige URL zu Ihrer PlentyMarkets-Instanz',
            ),
            new IntegrationField(
                key: 'plenty_username',
                label: 'Benutzername',
                type: 'text',
                valueType: 'string',
                required: true,
            ),
            new IntegrationField(
                key: 'plenty_password',
                label: 'Passwort',
                type: 'password',
                valueType: 'string',
                required: true,
                secret: true,
            ),
            new IntegrationField(
                key: 'plenty_access_token',
                label: 'Access Token (Optional)',
                type: 'password',
                valueType: 'string',
                secret: true,
                help: 'Falls vorhanden, wird Token-Auth bevorzugt',
            ),
            new IntegrationField(
                key: 'plenty_timeout',
                label: 'Timeout (Sek.)',
                type: 'number',
                valueType: 'float',
                default: 10.0,
            ),
            new IntegrationField(
                key: 'plenty_connect_timeout',
                label: 'Connect Timeout (Sek.)',
                type: 'number',
                valueType: 'float',
                default: 5.0,
            ),
            new IntegrationField(
                key: 'plenty_retry_times',
                label: 'Wiederholungen',
                type: 'number',
                valueType: 'int',
                default: 3,
                help: 'Anzahl der Wiederholungen bei Fehlern',
            ),
            new IntegrationField(
                key: 'plenty_verify_ssl',
                label: 'SSL-Zertifikate prüfen',
                type: 'checkbox',
                valueType: 'bool',
                default: true,
            ),
        ]);
    }

    public function validateConfiguration(array $configuration): bool
    {
        $required = ['plenty_base_url', 'plenty_username', 'plenty_password'];

        foreach ($required as $key) {
            if (empty($configuration[$key])) {
                return false;
            }
        }

        return filter_var($configuration['plenty_base_url'] ?? '', FILTER_VALIDATE_URL) !== false;
    }

    public function testConnection(array $configuration): bool
    {
        if (! $this->validateConfiguration($configuration)) {
            return false;
        }

        $baseUrl = (string) $configuration['plenty_base_url'];
        $timeoutSeconds = max(1.0, (float) ($configuration['plenty_connect_timeout'] ?? 5.0));
        $verifySsl = (bool) ($configuration['plenty_verify_ssl'] ?? true);

        try {
            // HEAD auf die Base-URL — prüft Erreichbarkeit ohne Auth-Endpunkt zu treffen.
            // Akzeptierte Erfolgs-Codes: 200..299, 401 (Auth-Required = Server reachable), 405 (HEAD nicht erlaubt = Server reachable).
            $response = Http::timeout($timeoutSeconds)
                ->connectTimeout($timeoutSeconds)
                ->withOptions(['verify' => $verifySsl])
                ->head(rtrim($baseUrl, '/'));

            $status = $response->status();

            return $status >= 200 && $status < 500 && $status !== 404;
        } catch (ConnectionException $exception) {
            Log::info('PlentyMarkets testConnection failed (connection)', [
                'reason' => $exception->getMessage(),
                'base_url' => $baseUrl,
            ]);

            return false;
        } catch (Throwable $exception) {
            Log::warning('PlentyMarkets testConnection failed (unexpected)', [
                'reason' => $exception->getMessage(),
                'class' => $exception::class,
            ]);

            return false;
        }
    }
}
