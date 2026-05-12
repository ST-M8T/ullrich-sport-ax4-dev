<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;

/**
 * DHL Freight party (spec: parties[]).
 *
 * Spec field-length limits:
 *   - name                            : 1..35
 *   - contactName                     : 0..35
 *   - phone                           : 0..22
 *   - email                           : 0..60   (basic format check)
 *   - vatEoriSocialSecurityNumber     : 0..35
 *
 * `id` (account number) is OPTIONAL on the VO; the use case enforces that the
 * payer party carries an id.
 */
final readonly class DhlParty
{
    private const MAX_NAME = 35;

    private const MAX_CONTACT_NAME = 35;

    private const MAX_PHONE = 22;

    private const MAX_EMAIL = 60;

    private const MAX_VAT = 35;

    public function __construct(
        public DhlPartyType $type,
        public string $name,
        public DhlAddress $address,
        public ?DhlAccountNumber $id = null,
        public ?string $contactName = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $vatEoriSocialSecurityNumber = null,
    ) {
        if (trim($name) === '') {
            throw DhlValueObjectException::invalid('party.name', 'must not be empty', $name);
        }
        $this->assertMax($name, self::MAX_NAME, 'party.name');

        if ($contactName !== null) {
            $this->assertMax($contactName, self::MAX_CONTACT_NAME, 'party.contactName');
        }
        if ($phone !== null) {
            $this->assertMax($phone, self::MAX_PHONE, 'party.phone');
        }
        if ($email !== null) {
            $this->assertMax($email, self::MAX_EMAIL, 'party.email');
            // Basic format check — RFC compliance not required for DHL.
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw DhlValueObjectException::invalid('party.email', 'must be a valid email address', $email);
            }
        }
        if ($vatEoriSocialSecurityNumber !== null) {
            $this->assertMax($vatEoriSocialSecurityNumber, self::MAX_VAT, 'party.vatEoriSocialSecurityNumber');
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $out = [
            'type' => $this->type->value,
            'name' => $this->name,
            'address' => $this->address->toArray(),
        ];
        if ($this->id !== null) {
            $out['id'] = $this->id->value;
        }
        if ($this->contactName !== null) {
            $out['contactName'] = $this->contactName;
        }
        if ($this->phone !== null) {
            $out['phone'] = $this->phone;
        }
        if ($this->email !== null) {
            $out['email'] = $this->email;
        }
        if ($this->vatEoriSocialSecurityNumber !== null) {
            $out['vatEoriSocialSecurityNumber'] = $this->vatEoriSocialSecurityNumber;
        }

        return $out;
    }

    private function assertMax(string $value, int $max, string $field): void
    {
        if (mb_strlen($value) > $max) {
            throw DhlValueObjectException::invalid($field, sprintf('max length %d', $max), $value);
        }
    }
}
