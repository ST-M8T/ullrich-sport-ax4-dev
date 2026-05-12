<?php

declare(strict_types=1);

namespace App\Http\Controllers\Configuration;

use App\Application\Integrations\IntegrationSettingsService;
use App\Support\Security\SecurityContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Integration Controller
 * SOLID: Single Responsibility - Nur Integration-Verwaltung
 * DDD: Application Layer - HTTP-Interface für Domain Services
 */
final class IntegrationController
{
    /**
     * Integrationen, deren Konfiguration nach Versand → DHL Freight
     * (admin.settings.dhl-freight.index) konsolidiert wurde. Aufrufe der
     * alten Routen werden umgeleitet, damit Bookmarks/Deep-Links bestehen
     * bleiben (Engineering-Handbuch §72: bestehende Schnittstellen respektieren).
     */
    private const REDIRECTED_TO_DHL_FREIGHT_SETTINGS = [
        'dhl_freight',
    ];

    public function __construct(
        private readonly IntegrationSettingsService $integrationService,
        private readonly Redirector $redirector,
    ) {}

    public function index(): View
    {
        $integrationsByType = $this->integrationService->getIntegrationsByType();

        return view('configuration.integrations.index', [
            'integrationsByType' => $integrationsByType,
        ]);
    }

    public function show(string $integrationKey): View|RedirectResponse
    {
        if (in_array($integrationKey, self::REDIRECTED_TO_DHL_FREIGHT_SETTINGS, true)) {
            return $this->redirectToDhlFreightSettings();
        }

        $integrationService = app(\App\Application\Integrations\IntegrationRegistry::class);
        $provider = $integrationService->get($integrationKey);

        if (! $provider) {
            return $this->redirector
                ->route('configuration-settings', ['tab' => 'integrations'])
                ->with('error', "Integration '{$integrationKey}' nicht gefunden.");
        }

        $configuration = $this->integrationService->getConfiguration($integrationKey);
        $schema = $provider->configurationSchema();

        return view('configuration.integrations.show', [
            'provider' => $provider,
            'schema' => $schema,
            'configuration' => $configuration,
        ]);
    }

    public function update(string $integrationKey, Request $request): RedirectResponse
    {
        if (in_array($integrationKey, self::REDIRECTED_TO_DHL_FREIGHT_SETTINGS, true)) {
            return $this->redirectToDhlFreightSettings();
        }

        $data = $request->validate([
            'configuration' => ['required', 'array'],
        ]);

        try {
            $this->integrationService->saveConfiguration(
                $integrationKey,
                $data['configuration'],
                (int) Auth::id(),
                SecurityContext::fromRequest($request)
            );

            return $this->redirector
                ->route('configuration-integrations.show', ['integrationKey' => $integrationKey])
                ->with('success', 'Konfiguration gespeichert.');
        } catch (\InvalidArgumentException $e) {
            return $this->redirector
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function test(string $integrationKey, Request $request): RedirectResponse
    {
        if (in_array($integrationKey, self::REDIRECTED_TO_DHL_FREIGHT_SETTINGS, true)) {
            return $this->redirectToDhlFreightSettings();
        }

        $data = $request->validate([
            'configuration' => ['required', 'array'],
        ]);

        $success = $this->integrationService->testConnection(
            $integrationKey,
            $data['configuration']
        );

        if ($success) {
            return $this->redirector
                ->back()
                ->with('success', 'Verbindung erfolgreich getestet.');
        }

        return $this->redirector
            ->back()
            ->with('error', 'Verbindungstest fehlgeschlagen.');
    }

    /**
     * Konsolidierte DHL-Freight-Settings leben unter Versand → DHL Freight.
     * Alte Integrations-Routen leiten dorthin um (Engineering-Handbuch §75:
     * eine Quelle der Wahrheit für DHL-Konfiguration).
     */
    private function redirectToDhlFreightSettings(): RedirectResponse
    {
        return $this->redirector
            ->route('admin.settings.dhl-freight.index')
            ->with('info', 'DHL Freight Einstellungen wurden zentralisiert unter Versand → DHL Freight.');
    }
}
