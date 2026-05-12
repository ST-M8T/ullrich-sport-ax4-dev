<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Orders\ValueObjects;

use InvalidArgumentException;

/**
 * Immutable value object representing the receiver (consignee) address of a shipment.
 *
 * Field-length limits follow DHL Freight `Address` spec:
 *   - street (combined name+number): max 50
 *   - cityName: max 35
 *   - postalCode: max 10
 *   - countryCode: ISO-3166-1 alpha-2 (exactly 2 chars, uppercase)
 *
 * Carrier-specific truncation must happen in the carrier mapper BEFORE constructing
 * this VO, so the domain always holds clean, valid receiver data.
 */
final class ShipmentReceiverAddress
{
    private const MAX_STREET = 50;
    private const MAX_CITY = 35;
    private const MAX_POSTAL_CODE = 10;
    private const MAX_COMPANY_NAME = 50;
    private const MAX_CONTACT_NAME = 50;
    private const MAX_ADDITIONAL_INFO = 50;
    private const MAX_EMAIL = 254;
    private const MAX_PHONE = 30;

    public function __construct(
        private readonly string $street,
        private readonly string $postalCode,
        private readonly string $cityName,
        private readonly string $countryCode,
        private readonly ?string $companyName = null,
        private readonly ?string $contactName = null,
        private readonly ?string $additionalAddressInfo = null,
        private readonly ?string $email = null,
        private readonly ?string $phone = null,
    ) {
        $this->assertNonEmpty($street, 'street');
        $this->assertNonEmpty($postalCode, 'postalCode');
        $this->assertNonEmpty($cityName, 'cityName');
        $this->assertNonEmpty($countryCode, 'countryCode');

        $this->assertMaxLength($street, self::MAX_STREET, 'street');
        $this->assertMaxLength($cityName, self::MAX_CITY, 'cityName');
        $this->assertMaxLength($postalCode, self::MAX_POSTAL_CODE, 'postalCode');

        if (strlen($countryCode) !== 2 || $countryCode !== strtoupper($countryCode) || ! ctype_alpha($countryCode)) {
            throw new InvalidArgumentException(sprintf(
                'Receiver countryCode must be ISO-3166-1 alpha-2 (2 uppercase letters), got "%s".',
                $countryCode
            ));
        }

        if ($companyName !== null) {
            $this->assertMaxLength($companyName, self::MAX_COMPANY_NAME, 'companyName');
        }
        if ($contactName !== null) {
            $this->assertMaxLength($contactName, self::MAX_CONTACT_NAME, 'contactName');
        }
        if ($additionalAddressInfo !== null) {
            $this->assertMaxLength($additionalAddressInfo, self::MAX_ADDITIONAL_INFO, 'additionalAddressInfo');
        }
        if ($email !== null) {
            $this->assertMaxLength($email, self::MAX_EMAIL, 'email');
        }
        if ($phone !== null) {
            $this->assertMaxLength($phone, self::MAX_PHONE, 'phone');
        }
    }

    public static function create(
        string $street,
        string $postalCode,
        string $cityName,
        string $countryCode,
        ?string $companyName = null,
        ?string $contactName = null,
        ?string $additionalAddressInfo = null,
        ?string $email = null,
        ?string $phone = null,
    ): self {
        return new self(
            trim($street),
            trim($postalCode),
            trim($cityName),
            strtoupper(trim($countryCode)),
            self::trimOrNull($companyName),
            self::trimOrNull($contactName),
            self::trimOrNull($additionalAddressInfo),
            self::trimOrNull($email),
            self::trimOrNull($phone),
        );
    }

    public function street(): string
    {
        return $this->street;
    }

    public function postalCode(): string
    {
        return $this->postalCode;
    }

    public function cityName(): string
    {
        return $this->cityName;
    }

    public function countryCode(): string
    {
        return $this->countryCode;
    }

    public function companyName(): ?string
    {
        return $this->companyName;
    }

    public function contactName(): ?string
    {
        return $this->contactName;
    }

    public function additionalAddressInfo(): ?string
    {
        return $this->additionalAddressInfo;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    /**
     * @return array{street:string,postalCode:string,cityName:string,countryCode:string,companyName:?string,contactName:?string,additionalAddressInfo:?string,email:?string,phone:?string}
     */
    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'postalCode' => $this->postalCode,
            'cityName' => $this->cityName,
            'countryCode' => $this->countryCode,
            'companyName' => $this->companyName,
            'contactName' => $this->contactName,
            'additionalAddressInfo' => $this->additionalAddressInfo,
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }

    private function assertNonEmpty(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException(sprintf(
                'Receiver %s must not be empty.',
                $field
            ));
        }
    }

    private function assertMaxLength(string $value, int $max, string $field): void
    {
        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException(sprintf(
                'Receiver %s exceeds max length of %d (got %d).',
                $field,
                $max,
                mb_strlen($value)
            ));
        }
    }

    private static function trimOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
