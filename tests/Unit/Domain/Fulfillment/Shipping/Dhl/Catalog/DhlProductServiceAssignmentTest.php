<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Shipping\Dhl\Catalog;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProductServiceAssignment;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions\InvalidParameterException;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\CountryCode;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlCatalogSource;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlServiceRequirement;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\JsonSchema;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPayerCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use PHPUnit\Framework\TestCase;

final class DhlProductServiceAssignmentTest extends TestCase
{
    private function schema(): JsonSchema
    {
        return JsonSchema::fromArray([
            'type' => 'object',
            'properties' => ['amount' => ['type' => 'number', 'minimum' => 0]],
            'required' => ['amount'],
        ]);
    }

    public function test_create_with_valid_defaults(): void
    {
        $a = DhlProductServiceAssignment::create(
            new DhlProductCode('STD'),
            'COD',
            new CountryCode('DE'),
            new CountryCode('AT'),
            DhlPayerCode::DAP,
            DhlServiceRequirement::ALLOWED,
            ['amount' => 50.0],
            DhlCatalogSource::SEED,
            null,
            $this->schema(),
        );

        self::assertSame('STD', $a->productCode()->value);
        self::assertSame('COD', $a->serviceCode());
        self::assertSame(3, $a->specificity());
    }

    public function test_create_with_invalid_defaults_throws(): void
    {
        $this->expectException(InvalidParameterException::class);
        DhlProductServiceAssignment::create(
            new DhlProductCode('STD'),
            'COD',
            null, null, null,
            DhlServiceRequirement::ALLOWED,
            ['amount' => -1],
            DhlCatalogSource::SEED,
            null,
            $this->schema(),
        );
    }

    public function test_specificity_zero_for_global(): void
    {
        $a = new DhlProductServiceAssignment(
            new DhlProductCode('STD'), 'COD',
            null, null, null,
            DhlServiceRequirement::ALLOWED,
            [],
            DhlCatalogSource::SEED, null,
        );

        self::assertSame(0, $a->specificity());
    }

    public function test_specificity_partial(): void
    {
        $a = new DhlProductServiceAssignment(
            new DhlProductCode('STD'), 'COD',
            new CountryCode('DE'), null, DhlPayerCode::DAP,
            DhlServiceRequirement::ALLOWED,
            [],
            DhlCatalogSource::SEED, null,
        );

        self::assertSame(2, $a->specificity());
    }

    public function test_matches_truth_table(): void
    {
        $global = new DhlProductServiceAssignment(
            new DhlProductCode('STD'), 'COD',
            null, null, null,
            DhlServiceRequirement::ALLOWED, [],
            DhlCatalogSource::SEED, null,
        );

        $specific = new DhlProductServiceAssignment(
            new DhlProductCode('STD'), 'COD',
            new CountryCode('DE'), new CountryCode('AT'), DhlPayerCode::DAP,
            DhlServiceRequirement::FORBIDDEN, [],
            DhlCatalogSource::SEED, null,
        );

        self::assertTrue($global->matches(
            new DhlProductCode('STD'),
            new CountryCode('FR'),
            new CountryCode('IT'),
            DhlPayerCode::DDP,
        ));

        self::assertFalse($specific->matches(
            new DhlProductCode('STD'),
            new CountryCode('FR'),
            new CountryCode('AT'),
            DhlPayerCode::DAP,
        ));

        self::assertTrue($specific->matches(
            new DhlProductCode('STD'),
            new CountryCode('DE'),
            new CountryCode('AT'),
            DhlPayerCode::DAP,
        ));

        // Different product code never matches
        self::assertFalse($global->matches(
            new DhlProductCode('EUC'),
            new CountryCode('DE'),
            new CountryCode('AT'),
            DhlPayerCode::DAP,
        ));
    }

    public function test_empty_service_code_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DhlProductServiceAssignment(
            new DhlProductCode('STD'), '',
            null, null, null,
            DhlServiceRequirement::ALLOWED, [],
            DhlCatalogSource::SEED, null,
        );
    }
}
