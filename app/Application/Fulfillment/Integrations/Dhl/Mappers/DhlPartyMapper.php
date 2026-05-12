<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Mappers;

use App\Domain\Fulfillment\Masterdata\FulfillmentSenderProfile;
use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlAccountNumber;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlParty;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPartyType;

/**
 * Stateless party mapper — single source of truth for converting domain
 * data (sender profile, shipment order receiver) into DHL Freight {@see DhlParty}
 * value objects.
 *
 * Truncation respects DHL party-spec field-length limits (the VO fails fast on
 * violations; this mapper truncates upstream domain data BEFORE handing it over).
 *   - name        : 35
 *   - contactName : 35
 *   - phone       : 22
 *   - email       : 60
 */
final class DhlPartyMapper
{
    private const MAX_NAME = 35;

    private const MAX_CONTACT_NAME = 35;

    private const MAX_PHONE = 22;

    private const MAX_EMAIL = 60;

    private const FALLBACK_NAME = '—';

    public static function consignorFromSenderProfile(
        FulfillmentSenderProfile $profile,
        ?DhlAccountNumber $accountNumber = null,
    ): DhlParty {
        return new DhlParty(
            type: DhlPartyType::Consignor,
            name: self::truncate($profile->companyName(), self::MAX_NAME),
            address: DhlAddressMapper::fromSenderProfile($profile),
            id: $accountNumber,
            contactName: self::truncateOrNull($profile->contactPerson(), self::MAX_CONTACT_NAME),
            phone: self::truncateOrNull($profile->phone(), self::MAX_PHONE),
            email: self::truncateOrNull($profile->email(), self::MAX_EMAIL),
        );
    }

    public static function consigneeFromOrder(ShipmentOrder $order): DhlParty
    {
        $receiver = $order->receiverAddress();
        if ($receiver === null) {
            // The assembler is responsible for fail-fast checks on missing receiver
            // data. Defensive guard so this mapper is callable in isolation.
            throw new \LogicException(
                'DhlPartyMapper::consigneeFromOrder requires a typed ShipmentReceiverAddress on the order.'
            );
        }

        $name = $receiver->companyName() ?? $receiver->contactName() ?? self::FALLBACK_NAME;

        return new DhlParty(
            type: DhlPartyType::Consignee,
            name: self::truncate($name, self::MAX_NAME),
            address: DhlAddressMapper::fromShipmentReceiverAddress($receiver),
            id: null,
            contactName: self::truncateOrNull($receiver->contactName(), self::MAX_CONTACT_NAME),
            phone: self::truncateOrNull(
                $receiver->phone() ?? $order->contactPhone(),
                self::MAX_PHONE,
            ),
            email: self::truncateOrNull(
                $receiver->email() ?? $order->contactEmail(),
                self::MAX_EMAIL,
            ),
        );
    }

    private static function truncate(string $value, int $max): string
    {
        $value = trim($value);
        if ($value === '') {
            return self::FALLBACK_NAME;
        }

        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }

    private static function truncateOrNull(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return mb_strlen($trimmed) > $max ? mb_substr($trimmed, 0, $max) : $trimmed;
    }
}
