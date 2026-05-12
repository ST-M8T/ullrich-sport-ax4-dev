<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;

/**
 * DHL Freight party address (spec: party.address).
 *
 * Spec field-length limits:
 *   - street                 : 1..50  (combined name + number)
 *   - cityName               : 1..35
 *   - postalCode             : 1..10
 *   - countryCode            : ISO-3166-1 alpha-2 (exactly 2 uppercase letters)
 *   - additionalAddressInfo  : 0..35  (optional)
 */
final readonly class DhlAddress
{
    private const MAX_STREET = 50;

    private const MAX_CITY = 35;

    private const MAX_POSTAL = 10;

    private const MAX_ADDITIONAL = 35;

    public function __construct(
        public string $street,
        public string $cityName,
        public string $postalCode,
        public string $countryCode,
        public ?string $additionalAddressInfo = null,
    ) {
        $this->assertNonEmpty($street, 'address.street');
        $this->assertNonEmpty($cityName, 'address.cityName');
        $this->assertNonEmpty($postalCode, 'address.postalCode');

        $this->assertMaxLength($street, self::MAX_STREET, 'address.street');
        $this->assertMaxLength($cityName, self::MAX_CITY, 'address.cityName');
        $this->assertMaxLength($postalCode, self::MAX_POSTAL, 'address.postalCode');

        if (mb_strlen($countryCode) !== 2 || $countryCode !== strtoupper($countryCode) || ! ctype_alpha($countryCode)) {
            throw DhlValueObjectException::invalid(
                'address.countryCode',
                'must be ISO-3166-1 alpha-2 (2 uppercase letters)',
                $countryCode,
            );
        }

        if ($additionalAddressInfo !== null) {
            $this->assertMaxLength($additionalAddressInfo, self::MAX_ADDITIONAL, 'address.additionalAddressInfo');
        }
    }

    /**
     * Compose street from optional name + number parts. Empty/whitespace
     * components are dropped. The composed string is then validated by the
     * regular constructor.
     */
    public static function compose(
        ?string $streetName,
        ?string $streetNumber,
        string $cityName,
        string $postalCode,
        string $countryCode,
        ?string $additionalAddressInfo = null,
    ): self {
        $name = $streetName !== null ? trim($streetName) : '';
        $number = $streetNumber !== null ? trim($streetNumber) : '';
        $combined = trim($name.' '.$number);

        return new self(
            street: $combined,
            cityName: trim($cityName),
            postalCode: trim($postalCode),
            countryCode: strtoupper(trim($countryCode)),
            additionalAddressInfo: $additionalAddressInfo !== null && trim($additionalAddressInfo) !== ''
                ? trim($additionalAddressInfo)
                : null,
        );
    }

    /**
     * @return array<string,string>
     */
    public function toArray(): array
    {
        $out = [
            'street' => $this->street,
            'cityName' => $this->cityName,
            'postalCode' => $this->postalCode,
            'countryCode' => $this->countryCode,
        ];
        if ($this->additionalAddressInfo !== null) {
            $out['additionalAddressInfo'] = $this->additionalAddressInfo;
        }

        return $out;
    }

    private function assertNonEmpty(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw DhlValueObjectException::invalid($field, 'must not be empty', $value);
        }
    }

    private function assertMaxLength(string $value, int $max, string $field): void
    {
        if (mb_strlen($value) > $max) {
            throw DhlValueObjectException::invalid($field, sprintf('max length %d', $max), $value);
        }
    }
}
