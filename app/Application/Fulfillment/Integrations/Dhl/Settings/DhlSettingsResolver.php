<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Settings;

use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentFreightProfileRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Configuration\DhlConfigurationRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Configuration\Exceptions\DhlConfigurationException;
use App\Domain\Shared\ValueObjects\Identifier;

/**
 * DhlSettingsResolver
 *
 * Application-Service: orchestriert die Auflösung der wirksamen DHL-Settings
 * aus den drei kanonischen Quellen (Architekt-Plan t5).
 *
 * Auflösungs-Reihenfolge AccountNumber:
 *   FulfillmentFreightProfile.account_number  >  DhlConfiguration.defaultAccountNumber
 *   → fehlt beides: Domain-Exception (Fail Fast §67).
 *
 * Stateless. Keine Eloquent-/HTTP-Imports — nur Repositories (DIP §8).
 */
final class DhlSettingsResolver
{
    public function __construct(
        private readonly DhlConfigurationRepository $configuration,
        private readonly FulfillmentFreightProfileRepository $freightProfiles,
    ) {}

    public function resolveAccountNumber(?int $freightProfileId = null): string
    {
        if ($freightProfileId !== null) {
            $profile = $this->freightProfiles->getById(Identifier::fromInt($freightProfileId));
            $profileAccount = $profile?->accountNumber();
            if ($profileAccount !== null && $profileAccount !== '') {
                return $profileAccount;
            }
        }

        $default = $this->configuration->load()->defaultAccountNumber();
        if ($default !== null && $default !== '') {
            return $default;
        }

        throw new DhlConfigurationException(
            'Keine DHL-Account-Number aufgelöst: weder im Freight-Profil noch als System-Default gesetzt.'
        );
    }

    public function resolveProductCode(int $freightProfileId): ?string
    {
        $profile = $this->freightProfiles->getById(Identifier::fromInt($freightProfileId));

        return $profile?->dhlProductId();
    }

    /**
     * @return array<int,string>
     */
    public function resolveDefaultServiceCodes(int $freightProfileId): array
    {
        $profile = $this->freightProfiles->getById(Identifier::fromInt($freightProfileId));
        if ($profile === null) {
            return [];
        }

        return $profile->dhlDefaultServiceCodes();
    }
}
