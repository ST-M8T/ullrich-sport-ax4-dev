<?php

declare(strict_types=1);

namespace App\Application\Integrations;

use App\Application\Configuration\SystemSettingService;
use App\Domain\Configuration\Contracts\SystemSettingRepository;
use App\Domain\Integrations\Contracts\IntegrationProvider;
use App\Support\Security\SecurityContext;

/**
 * Integration Settings Service
 * Verwaltet Integration-Konfigurationen über SystemSettings
 * SOLID: Single Responsibility - Nur Integration Settings
 * DDD: Application Service - Orchestriert Domain Services
 */
final class IntegrationSettingsService
{
    public function __construct(
        private readonly IntegrationRegistry $registry,
        private readonly SystemSettingService $settingService,
        private readonly SystemSettingRepository $settingRepository,
    ) {}

    /**
     * Gibt alle Integrationen gruppiert nach Typ zurück
     *
     * @return array<string, array<int, IntegrationProvider>>
     */
    public function getIntegrationsByType(): array
    {
        $integrations = [];
        $providers = $this->registry->all();

        foreach ($providers as $provider) {
            $typeKey = $provider->type()->value;
            if (! isset($integrations[$typeKey])) {
                $integrations[$typeKey] = [];
            }
            $integrations[$typeKey][] = $provider;
        }

        return $integrations;
    }

    /**
     * Gibt die Konfiguration einer Integration zurück
     *
     * @return array<string, mixed>
     */
    public function getConfiguration(string $integrationKey): array
    {
        $provider = $this->registry->get($integrationKey);
        if (! $provider) {
            return [];
        }

        $schema = $provider->configurationSchema();
        $configuration = [];

        foreach ($schema->fields() as $field) {
            $key = $field->key();
            $setting = $this->settingRepository->get($key);

            if ($setting !== null) {
                // Für Secrets: Wert nicht preisgeben, nur dass er gesetzt ist
                if ($field->isSecret()) {
                    $configuration[$key] = $setting->rawValue() !== null ? '***SET***' : null;
                } else {
                    $configuration[$key] = $setting->rawValue();
                }
            } else {
                $configuration[$key] = $field->default();
            }
        }

        return $configuration;
    }

    /**
     * Speichert die Konfiguration einer Integration
     *
     * @param  array<string, mixed>  $configuration
     */
    public function saveConfiguration(
        string $integrationKey,
        array $configuration,
        int $userId,
        SecurityContext $securityContext
    ): void {
        $provider = $this->registry->get($integrationKey);
        if (! $provider) {
            throw new \InvalidArgumentException("Integration '{$integrationKey}' nicht gefunden.");
        }

        if (! $provider->validateConfiguration($configuration)) {
            throw new \InvalidArgumentException("Ungültige Konfiguration für '{$integrationKey}'.");
        }

        $schema = $provider->configurationSchema();

        foreach ($schema->fields() as $field) {
            $key = $field->key();
            $value = $configuration[$key] ?? null;
            $valueType = $field->isSecret() ? 'secret' : $field->valueType();

            // Bei Secrets: Leer lassen = Wert behalten
            if ($field->isSecret() && ($value === null || $value === '')) {
                continue;
            }

            // Bei Checkbox: Boolean zu String konvertieren
            if ($field->type() === 'checkbox') {
                $value = ($value === '1' || $value === true || $value === 1) ? '1' : '0';
            }

            $this->settingService->set(
                $key,
                $value,
                $valueType,
                $userId,
                $securityContext
            );
        }
    }

    /**
     * Testet die Verbindung einer Integration
     *
     * @param  array<string, mixed>  $configuration
     */
    public function testConnection(string $integrationKey, array $configuration): bool
    {
        $provider = $this->registry->get($integrationKey);
        if (! $provider) {
            return false;
        }

        return $provider->testConnection($configuration);
    }
}
