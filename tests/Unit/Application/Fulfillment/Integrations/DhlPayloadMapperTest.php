<?php

namespace Tests\Unit\Application\Fulfillment\Integrations;

use App\Application\Fulfillment\Integrations\Dhl\Services\DhlPayloadMapper;
use App\Domain\Fulfillment\Orders\ShipmentPackage;
use App\Domain\Fulfillment\Orders\ValueObjects\PackageDimensions;
use App\Domain\Shared\ValueObjects\Identifier;
use PHPUnit\Framework\TestCase;

final class DhlPayloadMapperTest extends TestCase
{
    private DhlPayloadMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new DhlPayloadMapper(250.0);
    }

    public function test_calculate_volumetric_weight(): void
    {
        $dimensions = PackageDimensions::fromMillimetres(1000, 500, 500);
        $weight = $this->mapper->calculateVolumetricWeightFromDimensions($dimensions);
        $this->assertEquals(1000.0, $weight, 'Volumengewicht sollte 1000.0 kg sein');
    }

    public function test_calculate_volumetric_weight_with_custom_factor(): void
    {
        $dimensions = PackageDimensions::fromMillimetres(1000, 500, 500);
        $weight = $this->mapper->calculateVolumetricWeightFromDimensions($dimensions, 200.0);
        $this->assertEquals(1250.0, $weight, 'Mit Faktor 200 sollte das Gewicht höher sein');
    }

    public function test_calculate_volumetric_weight_for_package(): void
    {
        $package = ShipmentPackage::hydrate(
            Identifier::fromInt(1),
            Identifier::fromInt(1),
            null,
            null,
            1,
            10.0,
            1000,
            500,
            500,
            1,
        );

        $weight = $this->mapper->calculateVolumetricWeightForPackage($package);
        $this->assertNotNull($weight);
        $this->assertEquals(1000.0, $weight);
    }

    public function test_calculate_volumetric_weight_for_package_without_dimensions(): void
    {
        $package = ShipmentPackage::hydrate(
            Identifier::fromInt(1),
            Identifier::fromInt(1),
            null,
            null,
            1,
            10.0,
            null,
            null,
            null,
            1,
        );

        $weight = $this->mapper->calculateVolumetricWeightForPackage($package);
        $this->assertNull($weight);
    }

    public function test_determine_chargeable_weight_uses_higher_value(): void
    {
        $package = ShipmentPackage::hydrate(
            Identifier::fromInt(1),
            Identifier::fromInt(1),
            null,
            null,
            1,
            5.0,
            1000,
            500,
            500,
            1,
        );

        $weight = $this->mapper->determineChargeableWeight($package);
        $this->assertEquals(1000.0, $weight->value(), 'Volumengewicht (1000) ist höher als tatsächliches Gewicht (5)');
    }

    public function test_determine_chargeable_weight_uses_actual_weight_when_higher(): void
    {
        $package = ShipmentPackage::hydrate(
            Identifier::fromInt(1),
            Identifier::fromInt(1),
            null,
            null,
            1,
            2000.0,
            1000,
            500,
            500,
            1,
        );

        $weight = $this->mapper->determineChargeableWeight($package);
        $this->assertEquals(2000.0, $weight->value(), 'Tatsächliches Gewicht (2000) ist höher als Volumengewicht (1000)');
    }
}
