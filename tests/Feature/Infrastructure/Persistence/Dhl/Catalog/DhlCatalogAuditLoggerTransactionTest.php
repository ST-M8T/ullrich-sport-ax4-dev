<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Persistence\Dhl\Catalog;

use App\Application\Fulfillment\Integrations\Dhl\Catalog\DhlCatalogAuditLogger;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProduct;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlProductRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\AuditActor;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\CountryCode;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlCatalogSource;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlMarketAvailability;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DimensionLimits;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\WeightLimits;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPackageType;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlCatalogAuditLogModel;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlProductModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class DhlCatalogAuditLoggerTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_failure_rolls_back_product_save(): void
    {
        // Bind a logger that throws — this simulates an audit-write failure
        // inside the repository transaction. The product save MUST be rolled
        // back so audit-without-data is impossible.
        $this->app->bind(DhlCatalogAuditLogger::class, function () {
            return new class extends DhlCatalogAuditLogger {
                public function recordProductChange(
                    string $action,
                    string $entityKey,
                    ?DhlProduct $before,
                    ?DhlProduct $after,
                    AuditActor $actor,
                ): void {
                    throw new RuntimeException('audit boom');
                }
            };
        });

        /** @var DhlProductRepository $repo */
        $repo = $this->app->make(DhlProductRepository::class);

        $product = $this->makeProduct('TX1');

        try {
            $repo->save($product, AuditActor::system('test'));
            self::fail('expected RuntimeException');
        } catch (RuntimeException) {
            // expected
        }

        self::assertFalse(DhlProductModel::query()->whereKey('TX1')->exists());
        self::assertSame(0, DhlCatalogAuditLogModel::query()->where('entity_key', 'TX1')->count());
    }

    private function makeProduct(string $code): DhlProduct
    {
        return new DhlProduct(
            code: new DhlProductCode($code),
            name: 'X',
            description: '',
            marketAvailability: DhlMarketAvailability::B2B,
            fromCountries: [new CountryCode('DE')],
            toCountries: [new CountryCode('AT')],
            allowedPackageTypes: [new DhlPackageType('PLT')],
            weightLimits: new WeightLimits(0.0, 1000.0),
            dimensionLimits: new DimensionLimits(240.0, 120.0, 180.0),
            validFrom: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            validUntil: null,
            deprecatedAt: null,
            replacedByCode: null,
            source: DhlCatalogSource::SEED,
            syncedAt: null,
        );
    }
}
