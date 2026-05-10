<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Integrations\IntegrationRegistry;
use App\Application\Integrations\Providers\DhlFreightIntegrationProvider;
use App\Application\Integrations\Providers\EmonsIntegrationProvider;
use App\Application\Integrations\Providers\PlentyMarketsIntegrationProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Integration Service Provider
 * Registriert alle Integrationen im System
 * SOLID: Open/Closed - Neue Integrationen können einfach hinzugefügt werden
 * DDD: Infrastructure Layer - Registriert Domain Services
 */
final class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IntegrationRegistry::class, function ($app) {
            $registry = new IntegrationRegistry;

            // Registriere alle verfügbaren Integrationen
            $registry->register(new DhlFreightIntegrationProvider);
            $registry->register(new PlentyMarketsIntegrationProvider);
            $registry->register(new EmonsIntegrationProvider);

            // Weitere Integrationen können hier einfach hinzugefügt werden
            // $registry->register(new NeueIntegrationProvider());

            return $registry;
        });
    }

    public function boot(): void
    {
        //
    }
}
