<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Orders\ValueObjects;

use App\Domain\Fulfillment\Orders\ValueObjects\ShipmentReceiverAddress;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ShipmentReceiverAddressTest extends TestCase
{
    public function test_it_constructs_with_valid_required_fields(): void
    {
        $address = new ShipmentReceiverAddress(
            street: 'Hauptstraße 12',
            postalCode: '57462',
            cityName: 'Olpe',
            countryCode: 'DE',
        );

        self::assertSame('Hauptstraße 12', $address->street());
        self::assertSame('57462', $address->postalCode());
        self::assertSame('Olpe', $address->cityName());
        self::assertSame('DE', $address->countryCode());
        self::assertNull($address->companyName());
        self::assertNull($address->email());
    }

    public function test_create_factory_trims_and_uppercases_country(): void
    {
        $address = ShipmentReceiverAddress::create(
            street: '  Hauptstraße 12  ',
            postalCode: ' 57462 ',
            cityName: 'Olpe',
            countryCode: 'de',
            companyName: '  ',
        );

        self::assertSame('Hauptstraße 12', $address->street());
        self::assertSame('57462', $address->postalCode());
        self::assertSame('DE', $address->countryCode());
        self::assertNull($address->companyName(), 'Empty optional should normalize to null');
    }

    public function test_it_rejects_empty_street(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('street');

        new ShipmentReceiverAddress(
            street: '   ',
            postalCode: '57462',
            cityName: 'Olpe',
            countryCode: 'DE',
        );
    }

    public function test_it_rejects_empty_postal_code(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('postalCode');

        new ShipmentReceiverAddress(
            street: 'Hauptstraße 12',
            postalCode: '',
            cityName: 'Olpe',
            countryCode: 'DE',
        );
    }

    public function test_it_rejects_street_exceeding_50_chars(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('street');

        new ShipmentReceiverAddress(
            street: str_repeat('a', 51),
            postalCode: '57462',
            cityName: 'Olpe',
            countryCode: 'DE',
        );
    }

    public function test_it_rejects_city_exceeding_35_chars(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cityName');

        new ShipmentReceiverAddress(
            street: 'Hauptstraße 12',
            postalCode: '57462',
            cityName: str_repeat('x', 36),
            countryCode: 'DE',
        );
    }

    public function test_it_rejects_postal_code_exceeding_10_chars(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('postalCode');

        new ShipmentReceiverAddress(
            street: 'Hauptstraße 12',
            postalCode: '12345678901',
            cityName: 'Olpe',
            countryCode: 'DE',
        );
    }

    public function test_it_rejects_invalid_country_code_length(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('countryCode');

        new ShipmentReceiverAddress(
            street: 'Hauptstraße 12',
            postalCode: '57462',
            cityName: 'Olpe',
            countryCode: 'DEU',
        );
    }

    public function test_it_rejects_lowercase_country_code(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('countryCode');

        new ShipmentReceiverAddress(
            street: 'Hauptstraße 12',
            postalCode: '57462',
            cityName: 'Olpe',
            countryCode: 'de',
        );
    }

    public function test_it_rejects_non_alpha_country_code(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('countryCode');

        new ShipmentReceiverAddress(
            street: 'Hauptstraße 12',
            postalCode: '57462',
            cityName: 'Olpe',
            countryCode: 'D1',
        );
    }

    public function test_to_array_round_trip(): void
    {
        $address = new ShipmentReceiverAddress(
            street: 'Hauptstraße 12',
            postalCode: '57462',
            cityName: 'Olpe',
            countryCode: 'DE',
            companyName: 'Acme GmbH',
            contactName: 'Max Mustermann',
            additionalAddressInfo: 'Hinterhof',
            email: 'max@example.com',
            phone: '+49 2761 12345',
        );

        self::assertSame([
            'street' => 'Hauptstraße 12',
            'postalCode' => '57462',
            'cityName' => 'Olpe',
            'countryCode' => 'DE',
            'companyName' => 'Acme GmbH',
            'contactName' => 'Max Mustermann',
            'additionalAddressInfo' => 'Hinterhof',
            'email' => 'max@example.com',
            'phone' => '+49 2761 12345',
        ], $address->toArray());
    }
}
