<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Masterdata;

use App\Domain\Fulfillment\Masterdata\FulfillmentVariationProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FulfillmentVariationProfileTest extends TestCase
{
    public function test_hydrate_throws_for_unknown_default_state(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown default state 'shipped'.");

        FulfillmentVariationProfile::hydrate(
            Identifier::fromInt(1),
            100,
            null,
            null,
            'shipped',
            Identifier::fromInt(4),
            null,
            null,
        );
    }

    public function test_hydrate_lowercases_default_state(): void
    {
        $profile = FulfillmentVariationProfile::hydrate(
            Identifier::fromInt(1),
            100,
            null,
            null,
            '  KIT  ',
            Identifier::fromInt(4),
            null,
            null,
        );

        $this->assertSame('kit', $profile->defaultState());
        $this->assertTrue($profile->isDefaultKit());
        $this->assertFalse($profile->isDefaultAssembled());
    }

    public function test_negative_weight_is_clamped_to_zero(): void
    {
        $profile = FulfillmentVariationProfile::hydrate(
            Identifier::fromInt(1),
            100,
            null,
            null,
            'kit',
            Identifier::fromInt(4),
            -5.5,
            null,
        );

        $this->assertSame(0.0, $profile->defaultWeightKg());
    }

    public function test_variation_name_is_trimmed(): void
    {
        $profile = FulfillmentVariationProfile::hydrate(
            Identifier::fromInt(1),
            100,
            42,
            '  Schwarz - 28 Zoll  ',
            'assembled',
            Identifier::fromInt(4),
            null,
            null,
        );

        $this->assertSame('Schwarz - 28 Zoll', $profile->variationName());
        $this->assertTrue($profile->isDefaultAssembled());
        $this->assertSame(42, $profile->variationId());
    }

    public function test_null_weight_remains_null(): void
    {
        $profile = FulfillmentVariationProfile::hydrate(
            Identifier::fromInt(1),
            100,
            null,
            null,
            'kit',
            Identifier::fromInt(4),
            null,
            null,
        );

        $this->assertNull($profile->defaultWeightKg());
        $this->assertNull($profile->assemblyOptionId());
    }
}
