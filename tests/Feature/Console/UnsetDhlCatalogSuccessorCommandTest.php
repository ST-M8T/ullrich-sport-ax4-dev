<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @covers \App\Console\Commands\Dhl\Catalog\UnsetDhlCatalogSuccessorCommand
 */
final class UnsetDhlCatalogSuccessorCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function clears_successor_and_writes_audit(): void
    {
        $this->seedProduct('NEW', deprecated: false);
        $this->seedProduct('OLD', deprecated: true, successor: 'NEW');

        $this->artisan('dhl:catalog:unset-successor', [
            'oldCode' => 'OLD',
            '--actor' => 'ops@example.com',
        ])
            ->expectsOutputToContain('Successor for OLD cleared')
            ->assertExitCode(0);

        self::assertNull(DhlProductModel::query()->whereKey('OLD')->first()->replaced_by_code);

        $audit = DhlCatalogAuditLogModel::query()
            ->where('entity_key', 'OLD')
            ->where('actor', 'user:ops@example.com')
            ->where('action', DhlCatalogAuditLogger::ACTION_UPDATED)
            ->first();
        self::assertNotNull($audit);
        self::assertNull($audit->diff['changed']['replaced_by_code']['after']);
        self::assertSame('NEW', $audit->diff['changed']['replaced_by_code']['before']);
    }

    #[Test]
    public function fails_when_actor_option_is_missing(): void
    {
        $this->seedProduct('NEW', deprecated: false);
        $this->seedProduct('OLD', deprecated: true, successor: 'NEW');

        $this->artisan('dhl:catalog:unset-successor', [
            'oldCode' => 'OLD',
        ])
            ->expectsOutputToContain('--actor is required')
            ->assertExitCode(1);

        self::assertSame('NEW', DhlProductModel::query()->whereKey('OLD')->first()->replaced_by_code);
    }

    #[Test]
    public function works_on_active_products_too(): void
    {
        // Edge case: admin clears a successor erroneously set on an active product.
        $this->seedProduct('NEW', deprecated: false);
        $this->seedProduct('OLD', deprecated: false);
        DhlProductModel::query()->whereKey('OLD')->update(['replaced_by_code' => 'NEW']);

        $this->artisan('dhl:catalog:unset-successor', [
            'oldCode' => 'OLD',
            '--actor' => 'ops@example.com',
        ])->assertExitCode(0);

        self::assertNull(DhlProductModel::query()->whereKey('OLD')->first()->replaced_by_code);
    }

    #[Test]
    public function fails_when_product_does_not_exist(): void
    {
        $this->artisan('dhl:catalog:unset-successor', [
            'oldCode' => 'OLD',
            '--actor' => 'ops@example.com',
        ])
            ->expectsOutputToContain('not found in catalog')
            ->assertExitCode(1);
    }

    private function seedProduct(
        string $code,
        bool $deprecated,
        ?string $successor = null,
    ): void {
        $repo = $this->app->make(DhlProductRepository::class);
        $product = new DhlProduct(
            code: new DhlProductCode($code),
            name: 'Product '.$code,
            description: '',
            marketAvailability: DhlMarketAvailability::B2B,
            fromCountries: [new CountryCode('DE')],
            toCountries: [new CountryCode('AT')],
            allowedPackageTypes: [new DhlPackageType('PLT')],
            weightLimits: new WeightLimits(0.0, 1000.0),
            dimensionLimits: new DimensionLimits(240.0, 120.0, 180.0),
            validFrom: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            validUntil: null,
            deprecatedAt: $deprecated ? new DateTimeImmutable('2026-01-01T00:00:00Z') : null,
            replacedByCode: $successor !== null ? new DhlProductCode($successor) : null,
            source: DhlCatalogSource::SEED,
            syncedAt: null,
        );
        $repo->save($product, AuditActor::system('test-seed'));
    }
}
