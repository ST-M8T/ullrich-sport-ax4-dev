<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Masterdata;

use App\Domain\Shared\ValueObjects\Identifier;

final class FulfillmentSenderProfile
{
    private function __construct(
        private readonly Identifier $id,
        private readonly string $senderCode,
        private readonly string $displayName,
        private readonly string $companyName,
        private readonly ?string $contactPerson,
        private readonly ?string $email,
        private readonly ?string $phone,
        private readonly string $streetName,
        private readonly ?string $streetNumber,
        private readonly ?string $addressAddition,
        private readonly string $postalCode,
        private readonly string $city,
        private readonly string $countryIso2,
    ) {}

    public static function hydrate(
        Identifier $id,
        string $senderCode,
        string $displayName,
        string $companyName,
        ?string $contactPerson,
        ?string $email,
        ?string $phone,
        string $streetName,
        ?string $streetNumber,
        ?string $addressAddition,
        string $postalCode,
        string $city,
        string $countryIso2,
    ): self {
        return new self(
            $id,
            strtolower(trim($senderCode)),
            trim($displayName),
            trim($companyName),
            $contactPerson ? trim($contactPerson) : null,
            $email ? trim($email) : null,
            $phone ? trim($phone) : null,
            trim($streetName),
            $streetNumber ? trim($streetNumber) : null,
            $addressAddition ? trim($addressAddition) : null,
            trim($postalCode),
            trim($city),
            strtoupper(trim($countryIso2)),
        );
    }

    public function id(): Identifier
    {
        return $this->id;
    }

    public function senderCode(): string
    {
        return $this->senderCode;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function companyName(): string
    {
        return $this->companyName;
    }

    public function contactPerson(): ?string
    {
        return $this->contactPerson;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function streetName(): string
    {
        return $this->streetName;
    }

    public function streetNumber(): ?string
    {
        return $this->streetNumber;
    }

    public function addressAddition(): ?string
    {
        return $this->addressAddition;
    }

    public function postalCode(): string
    {
        return $this->postalCode;
    }

    public function city(): string
    {
        return $this->city;
    }

    public function countryIso2(): string
    {
        return $this->countryIso2;
    }
}
