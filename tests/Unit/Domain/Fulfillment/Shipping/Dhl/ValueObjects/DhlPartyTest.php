<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlAccountNumber;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlAddress;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlParty;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPartyType;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;
use PHPUnit\Framework\TestCase;

final class DhlPartyTest extends TestCase
{
    private function address(): DhlAddress
    {
        return new DhlAddress('Hauptstraße 12', 'Berlin', '10115', 'DE');
    }

    public function test_minimal_party_to_array_omits_null_fields(): void
    {
        $p = new DhlParty(DhlPartyType::Consignor, 'Ullrich Sport GmbH', $this->address());
        $arr = $p->toArray();
        self::assertSame('Consignor', $arr['type']);
        self::assertSame('Ullrich Sport GmbH', $arr['name']);
        self::assertArrayHasKey('address', $arr);
        self::assertArrayNotHasKey('id', $arr);
        self::assertArrayNotHasKey('contactName', $arr);
        self::assertArrayNotHasKey('phone', $arr);
        self::assertArrayNotHasKey('email', $arr);
        self::assertArrayNotHasKey('vatEoriSocialSecurityNumber', $arr);
    }

    public function test_full_party_to_array_includes_all_fields(): void
    {
        $p = new DhlParty(
            DhlPartyType::Consignee,
            'Empfänger AG',
            $this->address(),
            new DhlAccountNumber('ACC-1'),
            'Max Mustermann',
            '+49 30 123456',
            'kontakt@example.com',
            'DE123456789',
        );
        $arr = $p->toArray();
        self::assertSame('Consignee', $arr['type']);
        self::assertSame('ACC-1', $arr['id']);
        self::assertSame('Max Mustermann', $arr['contactName']);
        self::assertSame('+49 30 123456', $arr['phone']);
        self::assertSame('kontakt@example.com', $arr['email']);
        self::assertSame('DE123456789', $arr['vatEoriSocialSecurityNumber']);
    }

    public function test_empty_name_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlParty(DhlPartyType::Consignor, '   ', $this->address());
    }

    public function test_too_long_name_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlParty(DhlPartyType::Consignor, str_repeat('x', 36), $this->address());
    }

    public function test_invalid_email_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlParty(DhlPartyType::Consignor, 'Foo', $this->address(), email: 'not-an-email');
    }

    public function test_too_long_phone_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlParty(DhlPartyType::Consignor, 'Foo', $this->address(), phone: str_repeat('1', 23));
    }
}
