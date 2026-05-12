<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Settings;

use App\Domain\Fulfillment\Shipping\Dhl\Configuration\DhlConfiguration;
use App\Domain\Fulfillment\Shipping\Dhl\Configuration\DhlConfigurationRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Configuration\Exceptions\DhlConfigurationException;
use App\Domain\Integrations\Contracts\DhlAuthenticationGateway;
use App\Http\Requests\Admin\Settings\DhlFreightSettingsUpdateRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Konsolidierte Settings-Seite "Versand → DHL Freight".
 *
 * Loest die drei verstreuten DHL-Settings-Stellen (configuration/integrations,
 * configuration/settings tab "dhl", fulfillment/masterdata/freight) in einer
 * einzigen, fachlich konsolidierten Maske ab.
 *
 * Engineering-Handbuch §7: Presentation darf nur validieren, Use Case rufen,
 * Antwort formatieren — keine Fachlogik. Persistenz uebernimmt das Repository,
 * Invarianten das DhlConfiguration-Aggregate.
 */
final class DhlFreightSettingsController
{
    public function __construct(
        private readonly DhlConfigurationRepository $repository,
        private readonly DhlAuthenticationGateway $authGateway,
        private readonly Redirector $redirector,
    ) {}

    public function index(): View
    {
        $configuration = $this->repository->load();

        return view('admin.settings.dhl_freight.index', [
            'configuration' => $configuration,
            'configurationData' => $this->presentConfiguration($configuration),
        ]);
    }

    public function update(DhlFreightSettingsUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Read-modify-write: leere Secret-Felder behalten den existierenden Wert.
        // Diese "leer = nicht aendern"-Semantik liegt bewusst hier (Presentation),
        // nicht im Form Request — sie braucht den existierenden Aggregat-Zustand.
        $configuration = $this->repository->load();

        try {
            $configuration->setAuthBaseUrl((string) $validated['auth_base_url']);
            $configuration->setAuthClientId((string) $validated['auth_client_id']);
            if ($this->isFilledString($validated['auth_client_secret'] ?? null)) {
                $configuration->setAuthClientSecret((string) $validated['auth_client_secret']);
            }

            $configuration->setFreightBaseUrl((string) $validated['freight_base_url']);
            $configuration->setFreightApiKey((string) $validated['freight_api_key']);
            if ($this->isFilledString($validated['freight_api_secret'] ?? null)) {
                $configuration->setFreightApiSecret((string) $validated['freight_api_secret']);
            }

            $configuration->setDefaultAccountNumber($this->nullableString($validated['default_account_number'] ?? null));

            $configuration->setTrackingApiKey($this->nullableString($validated['tracking_api_key'] ?? null));
            $configuration->setTrackingDefaultService($this->nullableString($validated['tracking_default_service'] ?? null));
            $configuration->setTrackingOriginCountryCode($this->nullableString($validated['tracking_origin_country_code'] ?? null));
            $configuration->setTrackingRequesterCountryCode($this->nullableString($validated['tracking_requester_country_code'] ?? null));

            $configuration->setTimeoutSeconds((int) $validated['timeout_seconds']);
            $configuration->setVerifySsl((bool) $validated['verify_ssl']);

            $configuration->setPushBaseUrl($this->nullableString($validated['push_base_url'] ?? null));
            $configuration->setPushApiKey($this->nullableString($validated['push_api_key'] ?? null));

            $this->repository->save($configuration);
        } catch (DhlConfigurationException $e) {
            return $this->redirector
                ->route('admin.settings.dhl-freight.index')
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return $this->redirector
            ->route('admin.settings.dhl-freight.index')
            ->with('success', 'DHL Freight Konfiguration gespeichert.');
    }

    public function testConnection(): JsonResponse
    {
        try {
            $token = $this->authGateway->getToken();

            if (! is_array($token) || ! isset($token['access_token']) || ! is_string($token['access_token']) || $token['access_token'] === '') {
                return new JsonResponse([
                    'ok' => false,
                    'message' => 'DHL Auth lieferte keinen access_token.',
                ]);
            }

            return new JsonResponse([
                'ok' => true,
                'message' => 'Verbindung erfolgreich. access_token erhalten.',
            ]);
        } catch (Throwable $e) {
            Log::warning('DHL Freight Settings: testConnection fehlgeschlagen.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'ok' => false,
                'message' => 'Verbindung fehlgeschlagen: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function presentConfiguration(DhlConfiguration $configuration): array
    {
        return [
            'auth_base_url' => $configuration->authBaseUrl(),
            'auth_client_id' => $configuration->authClientId(),
            // Secrets niemals an die View zurueckgeben (§30 Logging-Regel-Geist,
            // §56 Frontend-Security). UI zeigt nur "vorhanden / nicht gesetzt".
            'auth_client_secret_set' => $configuration->authClientSecret() !== '',
            'freight_base_url' => $configuration->freightBaseUrl(),
            'freight_api_key' => $configuration->freightApiKey(),
            'freight_api_secret_set' => $configuration->freightApiSecret() !== '',
            'default_account_number' => $configuration->defaultAccountNumber(),
            'tracking_api_key_set' => $configuration->trackingApiKey() !== null,
            'tracking_default_service' => $configuration->trackingDefaultService(),
            'tracking_origin_country_code' => $configuration->trackingOriginCountryCode(),
            'tracking_requester_country_code' => $configuration->trackingRequesterCountryCode(),
            'timeout_seconds' => $configuration->timeoutSeconds(),
            'verify_ssl' => $configuration->verifySsl(),
            'push_base_url' => $configuration->pushBaseUrl(),
            'push_api_key_set' => $configuration->pushApiKey() !== null,
        ];
    }

    private function isFilledString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
