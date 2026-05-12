<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlAddress;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;
use PHPUnit\Framework\TestCase;

final class DhlAddressTest extends TestCase
{
    public function test_valid_address_to_array_matches_spec(): void
    {
        $a = new DhlAddress(
            street: 'Hauptstraße 12',
            cityName: 'Berlin',
            postalCode: '10115',
            countryCode: 'DE',
        );
        self::assertSame([
            'street' => 'Hauptstraße 12',
            'cityName' => 'Berlin',
            'postalCode' => '10115',
            'countryCode' => 'DE',
        ], $a->toArray());
    }

    public function test_optional_additional_info_is_included_when_present(): void
    {
        $a = new DhlAddress('Hauptstraße 12', 'Berlin', '10115', 'DE', '3. OG');
        self::assertSame('3. OG', $a->toArray()['additionalAddressInfo']);
    }

    public function test_compose_combines_name_and_number(): void
    {
        $a = DhlAddress::compose('Hauptstraße', '12', 'Berlin', '10115', 'de');
        self::assertSame('Hauptstraße 12', $a->street);
        self::assertSame('DE', $a->countryCode);
    }

    public function test_lowercase_country_code_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlAddress('Hauptstraße 12', 'Berlin', '10115', 'de');
    }

    public function test_three_letter_country_code_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlAddress('Hauptstraße 12', 'Berlin', '10115', 'DEU');
    }

    public function test_empty_street_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlAddress('', 'Berlin', '10115', 'DE');
    }

    public function test_too_long_street_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlAddress(str_repeat('x', 51), 'Berlin', '10115', 'DE');
    }

    public function test_too_long_postal_code_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlAddress('Hauptstraße 12', 'Berlin', '12345678901', 'DE');
    }
}
