<?php

declare(strict_types=1);

namespace App\Application\Integrations;

use App\Domain\Integrations\Contracts\IntegrationProvider;

/**
 * Integration Registry
 * Zentrale Registry für alle Integrationen
 * SOLID: Single Responsibility - Nur Registry-Verwaltung
 * DDD: Application Service - Orchestriert Domain Services
 */
final class IntegrationRegistry
{
    /**
     * @var array<string, IntegrationProvider>
     */
    private array $providers = [];

    /**
     * Registriert einen Integration Provider
     */
    public function register(IntegrationProvider $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    /**
     * Gibt alle registrierten Provider zurück
     *
     * @return array<string, IntegrationProvider>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * Gibt einen Provider anhand des Keys zurück
     */
    public function get(string $key): ?IntegrationProvider
    {
        return $this->providers[$key] ?? null;
    }

    /**
     * Prüft ob ein Provider registriert ist
     */
    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    /**
     * Gibt alle Provider eines bestimmten Typs zurück
     *
     * @return array<string, IntegrationProvider>
     */
    public function getByType(\App\Domain\Integrations\IntegrationType $type): array
    {
        return array_filter(
            $this->providers,
            fn (IntegrationProvider $provider) => $provider->type() === $type
        );
    }
}
