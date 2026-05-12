<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Mappers;

use App\Domain\Fulfillment\Masterdata\FulfillmentSenderProfile;
use App\Domain\Fulfillment\Orders\ValueObjects\ShipmentReceiverAddress;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlAddress;

/**
 * Stateless address mapper — single source of truth for converting domain
 * addresses (sender profile, receiver address VO) into DHL Freight {@see DhlAddress}
 * value objects.
 *
 * Truncation respects DHL spec field-length limits:
 *   - street: 50
 *   - cityName: 35
 *   - postalCode: 10
 *   - additionalAddressInfo: 35
 *
 * The VO itself fails fast on violations; this mapper truncates upstream data
 * (Plenty input is often longer than DHL allows) BEFORE handing it to the VO.
 */
final class DhlAddressMapper
{
    private const MAX_STREET = 50;

    private const MAX_CITY = 35;

    private const MAX_POSTAL = 10;

    private const MAX_ADDITIONAL = 35;

    public static function fromShipmentReceiverAddress(ShipmentReceiverAddress $address): DhlAddress
    {
        return new DhlAddress(
            street: self::truncate($address->street(), self::MAX_STREET),
            cityName: self::truncate($address->cityName(), self::MAX_CITY),
            postalCode: self::truncate($address->postalCode(), self::MAX_POSTAL),
            countryCode: strtoupper($address->countryCode()),
            additionalAddressInfo: self::truncateOrNull($address->additionalAddressInfo(), self::MAX_ADDITIONAL),
        );
    }

    public static function fromSenderProfile(FulfillmentSenderProfile $profile): DhlAddress
    {
        $street = self::composeStreet($profile->streetName(), $profile->streetNumber());

        return new DhlAddress(
            street: self::truncate($street, self::MAX_STREET),
            cityName: self::truncate($profile->city(), self::MAX_CITY),
            postalCode: self::truncate($profile->postalCode(), self::MAX_POSTAL),
            countryCode: strtoupper($profile->countryIso2()),
            additionalAddressInfo: self::truncateOrNull($profile->addressAddition(), self::MAX_ADDITIONAL),
        );
    }

    /**
     * Compose street from optional name + number parts. Returns trimmed
     * concatenation; empty parts are dropped.
     */
    public static function composeStreet(?string $name, ?string $number): string
    {
        $n = $name !== null ? trim($name) : '';
        $no = $number !== null ? trim($number) : '';

        return trim($n.' '.$no);
    }

    private static function truncate(string $value, int $max): string
    {
        $value = trim($value);

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
