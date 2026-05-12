<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Configuration;

use App\Domain\Fulfillment\Shipping\Dhl\Configuration\Exceptions\DhlConfigurationException;

/**
 * DHL Configuration Aggregate
 *
 * Konsolidiert die DHL Freight Konfiguration aus drei kanonischen Quellen
 * (siehe DhlConfigurationRepository) in ein einziges Domain-Aggregate.
 *
 * Pures Domain-Objekt: keine Framework-/DB-/Eloquent-Imports. Invarianten
 * werden in den Settern erzwungen, um ungültige Zustände früh zu verhindern
 * (Engineering-Handbuch §67 Fail Fast).
 */
final class DhlConfiguration
{
    private string $authBaseUrl;

    private string $authClientId;

    private string $authClientSecret;

    private string $freightBaseUrl;

    private string $freightApiKey;

    private string $freightApiSecret;

    private ?string $defaultAccountNumber = null;

    private ?string $trackingApiKey = null;

    private ?string $trackingDefaultService = null;

    private ?string $trackingOriginCountryCode = null;

    private ?string $trackingRequesterCountryCode = null;

    private int $timeoutSeconds = 10;

    private bool $verifySsl = true;

    private ?string $pushBaseUrl = null;

    private ?string $pushApiKey = null;

    private function __construct() {}

    public static function create(
        string $authBaseUrl,
        string $authClientId,
        string $authClientSecret,
        string $freightBaseUrl,
        string $freightApiKey,
        string $freightApiSecret,
    ): self {
        $config = new self;
        $config->setAuthBaseUrl($authBaseUrl);
        $config->setAuthClientId($authClientId);
        $config->setAuthClientSecret($authClientSecret);
        $config->setFreightBaseUrl($freightBaseUrl);
        $config->setFreightApiKey($freightApiKey);
        $config->setFreightApiSecret($freightApiSecret);

        return $config;
    }

    // --- Auth ---------------------------------------------------------------

    public function authBaseUrl(): string
    {
        return $this->authBaseUrl;
    }

    public function setAuthBaseUrl(string $value): void
    {
        $this->authBaseUrl = $this->ensureValidUrl($value, 'authBaseUrl');
    }

    public function authClientId(): string
    {
        return $this->authClientId;
    }

    public function setAuthClientId(string $value): void
    {
        $value = trim($value);
        if ($value === '') {
            throw new DhlConfigurationException('authClientId darf nicht leer sein.');
        }
        $this->authClientId = $value;
    }

    public function authClientSecret(): string
    {
        return $this->authClientSecret;
    }

    public function setAuthClientSecret(string $value): void
    {
        if ($value === '') {
            throw new DhlConfigurationException('authClientSecret darf nicht leer sein.');
        }
        $this->authClientSecret = $value;
    }

    // --- Freight ------------------------------------------------------------

    public function freightBaseUrl(): string
    {
        return $this->freightBaseUrl;
    }

    public function setFreightBaseUrl(string $value): void
    {
        $this->freightBaseUrl = $this->ensureValidUrl($value, 'freightBaseUrl');
    }

    public function freightApiKey(): string
    {
        return $this->freightApiKey;
    }

    public function setFreightApiKey(string $value): void
    {
        $value = trim($value);
        if ($value === '') {
            throw new DhlConfigurationException('freightApiKey darf nicht leer sein.');
        }
        $this->freightApiKey = $value;
    }

    public function freightApiSecret(): string
    {
        return $this->freightApiSecret;
    }

    public function setFreightApiSecret(string $value): void
    {
        if ($value === '') {
            throw new DhlConfigurationException('freightApiSecret darf nicht leer sein.');
        }
        $this->freightApiSecret = $value;
    }

    // --- Default Account Number --------------------------------------------

    public function defaultAccountNumber(): ?string
    {
        return $this->defaultAccountNumber;
    }

    public function setDefaultAccountNumber(?string $value): void
    {
        if ($value === null) {
            $this->defaultAccountNumber = null;

            return;
        }
        $value = trim($value);
        $this->defaultAccountNumber = $value === '' ? null : $value;
    }

    // --- Tracking -----------------------------------------------------------

    public function trackingApiKey(): ?string
    {
        return $this->trackingApiKey;
    }

    public function setTrackingApiKey(?string $value): void
    {
        $this->trackingApiKey = $this->emptyToNull($value);
    }

    public function trackingDefaultService(): ?string
    {
        return $this->trackingDefaultService;
    }

    public function setTrackingDefaultService(?string $value): void
    {
        $this->trackingDefaultService = $this->emptyToNull($value);
    }

    public function trackingOriginCountryCode(): ?string
    {
        return $this->trackingOriginCountryCode;
    }

    public function setTrackingOriginCountryCode(?string $value): void
    {
        $this->trackingOriginCountryCode = $this->ensureValidCountryCode($value, 'trackingOriginCountryCode');
    }

    public function trackingRequesterCountryCode(): ?string
    {
        return $this->trackingRequesterCountryCode;
    }

    public function setTrackingRequesterCountryCode(?string $value): void
    {
        $this->trackingRequesterCountryCode = $this->ensureValidCountryCode($value, 'trackingRequesterCountryCode');
    }

    // --- Misc ---------------------------------------------------------------

    public function timeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }

    public function setTimeoutSeconds(int $value): void
    {
        if ($value <= 0) {
            throw new DhlConfigurationException('timeoutSeconds muss > 0 sein.');
        }
        $this->timeoutSeconds = $value;
    }

    public function verifySsl(): bool
    {
        return $this->verifySsl;
    }

    public function setVerifySsl(bool $value): void
    {
        $this->verifySsl = $value;
    }

    public function pushBaseUrl(): ?string
    {
        return $this->pushBaseUrl;
    }

    public function setPushBaseUrl(?string $value): void
    {
        if ($value === null || trim($value) === '') {
            $this->pushBaseUrl = null;

            return;
        }
        $this->pushBaseUrl = $this->ensureValidUrl($value, 'pushBaseUrl');
    }

    public function pushApiKey(): ?string
    {
        return $this->pushApiKey;
    }

    public function setPushApiKey(?string $value): void
    {
        $this->pushApiKey = $this->emptyToNull($value);
    }

    // --- Invarianten-Helpers ------------------------------------------------

    private function ensureValidUrl(string $value, string $field): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new DhlConfigurationException("{$field} darf nicht leer sein.");
        }
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new DhlConfigurationException("{$field} ist keine gültige URL: {$value}");
        }

        return $value;
    }

    private function ensureValidCountryCode(?string $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (! preg_match('/^[A-Z]{2}$/', $value)) {
            throw new DhlConfigurationException("{$field} muss ein ISO-3166-1 Alpha-2 Code sein (z.B. DE).");
        }

        return $value;
    }

    private function emptyToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
