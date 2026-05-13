<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Masterdata;

use App\Domain\Fulfillment\Masterdata\FulfillmentPackagingProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use PHPUnit\Framework\TestCase;

final class FulfillmentPackagingProfileTest extends TestCase
{
    public function test_negative_dimensions_are_clamped_to_zero(): void
    {
        $profile = FulfillmentPackagingProfile::hydrate(
            Identifier::fromInt(1),
            'Box',
            null,
            -10,
            -20,
            -30,
            1,
            1,
            1,
            1,
            1,
            null,
        );

        $this->assertSame(0, $profile->lengthMillimetres());
        $this->assertSame(0, $profile->widthMillimetres());
        $this->assertSame(0, $profile->heightMillimetres());
    }

    public function test_pallet_and_stack_units_have_min_one(): void
    {
        $profile = FulfillmentPackagingProfile::hydrate(
            Identifier::fromInt(1),
            'Box',
            null,
            100,
            100,
            100,
            0,
            0,
            0,
            0,
            0,
            null,
        );

        $this->assertSame(1, $profile->truckSlotUnits());
        $this->assertSame(1, $profile->maxUnitsPerPalletSameRecipient());
        $this->assertSame(1, $profile->maxUnitsPerPalletMixedRecipient());
        $this->assertSame(1, $profile->maxStackablePalletsSameRecipient());
        $this->assertSame(1, $profile->maxStackablePalletsMixedRecipient());
    }

    public function test_package_name_and_optional_code_are_trimmed(): void
    {
        $profile = FulfillmentPackagingProfile::hydrate(
            Identifier::fromInt(1),
            '  Eurobox  ',
            '  EUR-1  ',
            600,
            400,
            300,
            2,
            10,
            10,
            3,
            2,
            '  Standard EU pallet  ',
        );

        $this->assertSame('Eurobox', $profile->packageName());
        $this->assertSame('EUR-1', $profile->packagingCode());
        $this->assertSame('Standard EU pallet', $profile->notes());
    }

    public function test_empty_optional_strings_become_null(): void
    {
        $profile = FulfillmentPackagingProfile::hydrate(
            Identifier::fromInt(1),
            'Box',
            null,
            100, 100, 100, 1, 1, 1, 1, 1,
            null,
        );

        $this->assertNull($profile->packagingCode());
        $this->assertNull($profile->notes());
    }
}
