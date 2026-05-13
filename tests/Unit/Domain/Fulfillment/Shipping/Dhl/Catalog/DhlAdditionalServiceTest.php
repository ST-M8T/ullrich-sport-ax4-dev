<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Shipping\Dhl\Catalog;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlAdditionalService;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions\InvalidParameterException;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlCatalogSource;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlServiceCategory;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\JsonSchema;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DhlAdditionalServiceTest extends TestCase
{
    private function buildService(): DhlAdditionalService
    {
        return new DhlAdditionalService(
            code: 'COD',
            name: 'Cash on Delivery',
            description: 'Collect payment on delivery',
            category: DhlServiceCategory::DELIVERY,
            parameterSchema: JsonSchema::fromArray([
                'type' => 'object',
                'properties' => [
                    'amount' => ['type' => 'number', 'minimum' => 0],
                    'currency' => ['type' => 'string', 'enum' => ['EUR', 'USD']],
                ],
                'required' => ['amount', 'currency'],
            ]),
            deprecatedAt: null,
            source: DhlCatalogSource::SEED,
            syncedAt: null,
        );
    }

    public function test_construct_ok(): void
    {
        $s = $this->buildService();
        self::assertSame('COD', $s->code());
        self::assertSame(DhlServiceCategory::DELIVERY, $s->category());
        self::assertFalse($s->isDeprecated());
    }

    public function test_validate_parameters_ok(): void
    {
        $this->buildService()->validateParameters(['amount' => 99.99, 'currency' => 'EUR']);
        $this->expectNotToPerformAssertions();
    }

    public function test_validate_parameters_fails(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->buildService()->validateParameters(['amount' => -1, 'currency' => 'EUR']);
    }

    public function test_empty_code_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DhlAdditionalService(
            '', 'x', '', DhlServiceCategory::SPECIAL,
            JsonSchema::fromArray(['type' => 'object']),
            null, DhlCatalogSource::SEED, null,
        );
    }

    public function test_too_long_code_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DhlAdditionalService(
            'TOOLONGCODE', 'x', '', DhlServiceCategory::SPECIAL,
            JsonSchema::fromArray(['type' => 'object']),
            null, DhlCatalogSource::SEED, null,
        );
    }

    public function test_lowercase_code_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DhlAdditionalService(
            'cod', 'x', '', DhlServiceCategory::SPECIAL,
            JsonSchema::fromArray(['type' => 'object']),
            null, DhlCatalogSource::SEED, null,
        );
    }

    public function test_deprecate_and_restore(): void
    {
        $s = $this->buildService();
        $s->deprecate(new DateTimeImmutable('2026-01-01'));
        self::assertTrue($s->isDeprecated());
        $s->restore();
        self::assertFalse($s->isDeprecated());
    }
}
