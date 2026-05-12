<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Fulfillment\Shipping\Dhl;

use App\Application\Configuration\SystemSettingService;
use App\Domain\Fulfillment\Shipping\Dhl\Configuration\DhlConfiguration;
use App\Domain\Fulfillment\Shipping\Dhl\Configuration\DhlConfigurationRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Configuration\Exceptions\DhlConfigurationException;
use Illuminate\Support\Facades\DB;

/**
 * EloquentDhlConfigurationRepository — Fassade über die drei kanonischen
 * Quellen für DHL-Settings (Architekt-Plan t5):
 *
 *  - system_settings.dhl_auth_*           → OAuth (clientId/clientSecret/Base-URL)
 *  - system_settings.dhl_freight_*        → Freight Endpoints + Misc
 *  - system_settings.dhl_*                → Tracking + Default Account Number
 *  - system_settings.dhl_push_*           → Push Notifications
 *
 * Es existiert KEINE separate `integrations`-Tabelle für DHL-Freight: die
 * IntegrationProvider-Werte (`dhl_freight_*`) werden über IntegrationSettingsService
 * ebenfalls in `system_settings` persistiert. Damit ist `system_settings` die
 * einzige technische Persistenzschicht — die Trennung der "drei Quellen" erfolgt
 * über Key-Präfixe, nicht über Tabellen.
 *
 * SOLID/DDD: Domain kennt nur das Interface (Engineering-Handbuch §8/§11).
 */
final class EloquentDhlConfigurationRepository implements DhlConfigurationRepository
{
    // --- Auth ---------------------------------------------------------------
    private const KEY_AUTH_BASE_URL = 'dhl_auth_base_url';

    private const KEY_AUTH_CLIENT_ID = 'dhl_auth_username';

    private const KEY_AUTH_CLIENT_SECRET = 'dhl_auth_password';

    // --- Freight ------------------------------------------------------------
    private const KEY_FREIGHT_BASE_URL = 'dhl_freight_base_url';

    private const KEY_FREIGHT_API_KEY = 'dhl_freight_api_key';

    private const KEY_FREIGHT_API_SECRET = 'dhl_freight_api_secret';

    private const KEY_FREIGHT_TIMEOUT = 'dhl_freight_timeout';

    private const KEY_FREIGHT_VERIFY_SSL = 'dhl_freight_verify_ssl';

    // --- Default Account ---------------------------------------------------
    private const KEY_DEFAULT_ACCOUNT_NUMBER = 'dhl_default_account_number';

    // --- Tracking -----------------------------------------------------------
    private const KEY_TRACKING_API_KEY = 'dhl_api_key';

    private const KEY_TRACKING_DEFAULT_SERVICE = 'dhl_default_service';

    private const KEY_TRACKING_ORIGIN_COUNTRY = 'dhl_origin_country_code';

    private const KEY_TRACKING_REQUESTER_COUNTRY = 'dhl_requester_country_code';

    // --- Push ---------------------------------------------------------------
    private const KEY_PUSH_BASE_URL = 'dhl_push_base_url';

    private const KEY_PUSH_API_KEY = 'dhl_push_api_key';

    public function __construct(private readonly SystemSettingService $settings) {}

    public function load(): DhlConfiguration
    {
        $authBaseUrl = $this->required(self::KEY_AUTH_BASE_URL);
        $authClientId = $this->required(self::KEY_AUTH_CLIENT_ID);
        $authClientSecret = $this->required(self::KEY_AUTH_CLIENT_SECRET);
        $freightBaseUrl = $this->required(self::KEY_FREIGHT_BASE_URL);
        $freightApiKey = $this->required(self::KEY_FREIGHT_API_KEY);
        $freightApiSecret = $this->required(self::KEY_FREIGHT_API_SECRET);

        $config = DhlConfiguration::create(
            authBaseUrl: $authBaseUrl,
            authClientId: $authClientId,
            authClientSecret: $authClientSecret,
            freightBaseUrl: $freightBaseUrl,
            freightApiKey: $freightApiKey,
            freightApiSecret: $freightApiSecret,
        );

        $config->setDefaultAccountNumber($this->optional(self::KEY_DEFAULT_ACCOUNT_NUMBER));
        $config->setTrackingApiKey($this->optional(self::KEY_TRACKING_API_KEY));
        $config->setTrackingDefaultService($this->optional(self::KEY_TRACKING_DEFAULT_SERVICE));
        $config->setTrackingOriginCountryCode($this->optional(self::KEY_TRACKING_ORIGIN_COUNTRY));
        $config->setTrackingRequesterCountryCode($this->optional(self::KEY_TRACKING_REQUESTER_COUNTRY));

        $timeout = $this->optional(self::KEY_FREIGHT_TIMEOUT);
        if ($timeout !== null && $timeout !== '') {
            $config->setTimeoutSeconds(max(1, (int) $timeout));
        }

        $verify = $this->optional(self::KEY_FREIGHT_VERIFY_SSL);
        if ($verify !== null && $verify !== '') {
            $config->setVerifySsl($this->toBool($verify));
        }

        $config->setPushBaseUrl($this->optional(self::KEY_PUSH_BASE_URL));
        $config->setPushApiKey($this->optional(self::KEY_PUSH_API_KEY));

        return $config;
    }

    public function save(DhlConfiguration $configuration): void
    {
        DB::transaction(function () use ($configuration): void {
            // Auth (Secrets)
            $this->writeString(self::KEY_AUTH_BASE_URL, $configuration->authBaseUrl());
            $this->writeString(self::KEY_AUTH_CLIENT_ID, $configuration->authClientId());
            $this->writeSecret(self::KEY_AUTH_CLIENT_SECRET, $configuration->authClientSecret());

            // Freight Endpoints (Secrets)
            $this->writeString(self::KEY_FREIGHT_BASE_URL, $configuration->freightBaseUrl());
            $this->writeString(self::KEY_FREIGHT_API_KEY, $configuration->freightApiKey());
            $this->writeSecret(self::KEY_FREIGHT_API_SECRET, $configuration->freightApiSecret());

            // Default Account
            $this->writeNullableString(
                self::KEY_DEFAULT_ACCOUNT_NUMBER,
                $configuration->defaultAccountNumber(),
            );

            // Tracking
            $this->writeNullableSecret(self::KEY_TRACKING_API_KEY, $configuration->trackingApiKey());
            $this->writeNullableString(self::KEY_TRACKING_DEFAULT_SERVICE, $configuration->trackingDefaultService());
            $this->writeNullableString(self::KEY_TRACKING_ORIGIN_COUNTRY, $configuration->trackingOriginCountryCode());
            $this->writeNullableString(self::KEY_TRACKING_REQUESTER_COUNTRY, $configuration->trackingRequesterCountryCode());

            // Misc
            $this->writeString(self::KEY_FREIGHT_TIMEOUT, (string) $configuration->timeoutSeconds(), valueType: 'int');
            $this->writeString(self::KEY_FREIGHT_VERIFY_SSL, $configuration->verifySsl() ? '1' : '0', valueType: 'bool');

            // Push
            $this->writeNullableString(self::KEY_PUSH_BASE_URL, $configuration->pushBaseUrl());
            $this->writeNullableSecret(self::KEY_PUSH_API_KEY, $configuration->pushApiKey());
        });
    }

    private function required(string $key): string
    {
        $value = $this->settings->get($key);
        if ($value === null || $value === '') {
            throw new DhlConfigurationException("Pflicht-Einstellung '{$key}' ist nicht gesetzt.");
        }

        return $value;
    }

    private function optional(string $key): ?string
    {
        $value = $this->settings->get($key);

        return ($value === null || $value === '') ? null : $value;
    }

    private function writeString(string $key, string $value, string $valueType = 'string'): void
    {
        $this->settings->set($key, $value, $valueType);
    }

    private function writeNullableString(string $key, ?string $value): void
    {
        if ($value === null) {
            $this->settings->remove($key);

            return;
        }
        $this->settings->set($key, $value, 'string');
    }

    private function writeSecret(string $key, string $value): void
    {
        $this->settings->set($key, $value, 'secret');
    }

    private function writeNullableSecret(string $key, ?string $value): void
    {
        if ($value === null) {
            $this->settings->remove($key);

            return;
        }
        $this->settings->set($key, $value, 'secret');
    }

    private function toBool(string $value): bool
    {
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
}
