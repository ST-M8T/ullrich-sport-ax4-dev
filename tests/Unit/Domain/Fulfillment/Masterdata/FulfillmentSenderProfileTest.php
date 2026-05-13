<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Masterdata;

use App\Domain\Fulfillment\Masterdata\FulfillmentSenderProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use PHPUnit\Framework\TestCase;

final class FulfillmentSenderProfileTest extends TestCase
{
    public function test_sender_code_is_lowercased_and_country_uppercased(): void
    {
        $profile = $this->hydrate(senderCode: '  MAIN-DE  ', countryIso2: '  de  ');

        $this->assertSame('main-de', $profile->senderCode());
        $this->assertSame('DE', $profile->countryIso2());
    }

    public function test_required_fields_are_trimmed(): void
    {
        $profile = $this->hydrate(
            companyName: '  Ullrich Sport GmbH  ',
            streetName: '  Hauptstrasse  ',
            postalCode: '  12345  ',
            city: '  Berlin  ',
        );

        $this->assertSame('Ullrich Sport GmbH', $profile->companyName());
        $this->assertSame('Hauptstrasse', $profile->streetName());
        $this->assertSame('12345', $profile->postalCode());
        $this->assertSame('Berlin', $profile->city());
    }

    public function test_blank_optional_strings_remain_null_and_filled_are_trimmed(): void
    {
        $profile = $this->hydrate(
            contactPerson: null,
            email: '  ops@example.com  ',
            phone: null,
            streetNumber: '  12a  ',
            addressAddition: null,
        );

        $this->assertNull($profile->contactPerson());
        $this->assertSame('ops@example.com', $profile->email());
        $this->assertNull($profile->phone());
        $this->assertSame('12a', $profile->streetNumber());
        $this->assertNull($profile->addressAddition());
    }

    public function test_id_is_exposed(): void
    {
        $profile = $this->hydrate();

        $this->assertSame(42, $profile->id()->toInt());
    }

    private function hydrate(
        string $senderCode = 'main',
        string $displayName = 'Ullrich',
        string $companyName = 'Ullrich Sport',
        ?string $contactPerson = null,
        ?string $email = null,
        ?string $phone = null,
        string $streetName = 'Hauptstr.',
        ?string $streetNumber = '1',
        ?string $addressAddition = null,
        string $postalCode = '12345',
        string $city = 'Berlin',
        string $countryIso2 = 'DE',
    ): FulfillmentSenderProfile {
        return FulfillmentSenderProfile::hydrate(
            Identifier::fromInt(42),
            $senderCode,
            $displayName,
            $companyName,
            $contactPerson,
            $email,
            $phone,
            $streetName,
            $streetNumber,
            $addressAddition,
            $postalCode,
            $city,
            $countryIso2,
        );
    }
}
