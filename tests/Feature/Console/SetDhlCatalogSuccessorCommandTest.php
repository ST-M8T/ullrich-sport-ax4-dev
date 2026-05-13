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
 * @covers \App\Console\Commands\Dhl\Catalog\SetDhlCatalogSuccessorCommand
 * @covers \App\Application\Fulfillment\Integrations\Dhl\Catalog\DhlCatalogSuccessorMappingService
 */
final class SetDhlCatalogSuccessorCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function happy_path_sets_successor_and_writes_audit(): void
    {
        $this->seedProduct('OLD', deprecated: true);
        $this->seedProduct('NEW', deprecated: false);

        $this->artisan('dhl:catalog:set-successor', [
            'oldCode' => 'OLD',
            'newCode' => 'NEW',
            '--actor' => 'ops@example.com',
        ])
            ->expectsOutputToContain('Successor for OLD set to NEW')
            ->assertExitCode(0);

        $row = DhlProductModel::query()->whereKey('OLD')->first();
        self::assertNotNull($row);
        self::assertSame('NEW', $row->replaced_by_code);

        $audit = DhlCatalogAuditLogModel::query()
            ->where('entity_key', 'OLD')
            ->where('actor', 'user:ops@example.com')
            ->where('action', DhlCatalogAuditLogger::ACTION_UPDATED)
            ->first();
        self::assertNotNull($audit);
        self::assertSame('product', $audit->entity_type);
        self::assertArrayHasKey('changed', $audit->diff);
        self::assertArrayHasKey('replaced_by_code', $audit->diff['changed']);
        self::assertSame('NEW', $audit->diff['changed']['replaced_by_code']['after']);
    }

    #[Test]
    public function fails_when_actor_option_is_missing(): void
    {
        $this->seedProduct('OLD', deprecated: true);
        $this->seedProduct('NEW', deprecated: false);

        $this->artisan('dhl:catalog:set-successor', [
            'oldCode' => 'OLD',
            'newCode' => 'NEW',
        ])
            ->expectsOutputToContain('--actor is required')
            ->assertExitCode(1);

        self::assertNull(DhlProductModel::query()->whereKey('OLD')->first()->replaced_by_code);
    }

    #[Test]
    public function fails_when_actor_is_not_email(): void
    {
        $this->seedProduct('OLD', deprecated: true);
        $this->seedProduct('NEW', deprecated: false);

        $this->artisan('dhl:catalog:set-successor', [
            'oldCode' => 'OLD',
            'newCode' => 'NEW',
            '--actor' => 'not-an-email',
        ])
            ->expectsOutputToContain('--actor must be a valid email')
            ->assertExitCode(1);
    }

    #[Test]
    public function fails_when_old_code_is_not_deprecated(): void
    {
        $this->seedProduct('OLD', deprecated: false);
        $this->seedProduct('NEW', deprecated: false);

        $this->artisan('dhl:catalog:set-successor', [
            'oldCode' => 'OLD',
            'newCode' => 'NEW',
            '--actor' => 'ops@example.com',
        ])
            ->expectsOutputToContain('not deprecated')
            ->assertExitCode(1);
    }

    #[Test]
    public function fails_when_new_code_is_deprecated(): void
    {
        $this->seedProduct('OLD', deprecated: true);
        $this->seedProduct('NEW', deprecated: true);

        $this->artisan('dhl:catalog:set-successor', [
            'oldCode' => 'OLD',
            'newCode' => 'NEW',
            '--actor' => 'ops@example.com',
        ])
            ->expectsOutputToContain('cannot be used as a successor')
            ->assertExitCode(1);
    }

    #[Test]
    public function fails_when_old_code_does_not_exist(): void
    {
        $this->seedProduct('NEW', deprecated: false);

        $this->artisan('dhl:catalog:set-successor', [
            'oldCode' => 'OLD',
            'newCode' => 'NEW',
            '--actor' => 'ops@example.com',
        ])
            ->expectsOutputToContain('not found in catalog')
            ->assertExitCode(1);
    }

    #[Test]
    public function fails_when_new_code_does_not_exist(): void
    {
        $this->seedProduct('OLD', deprecated: true);

        $this->artisan('dhl:catalog:set-successor', [
            'oldCode' => 'OLD',
            'newCode' => 'NEW',
            '--actor' => 'ops@example.com',
        ])
            ->expectsOutputToContain('not found in catalog')
            ->assertExitCode(1);
    }

    #[Test]
    public function fails_on_circular_successor_chain(): void
    {
        // Construct a degenerate case where the proposed new code is active
        // but its chain already points back to old. (Such a chain could only
        // be created erroneously through direct DB writes — we still must
        // refuse to close the loop.)
        $this->seedProduct('AAA', deprecated: true);
        $this->seedProduct('BBB', deprecated: false);
        // Direct DB update bypassing the domain constructor — simulates a
        // pre-existing inconsistency that the cycle guard must catch.
        DhlProductModel::query()->whereKey('BBB')->update([
            'replaced_by_code' => 'AAA',
        ]);

        $this->artisan('dhl:catalog:set-successor', [
            'oldCode' => 'AAA',
            'newCode' => 'BBB',
            '--actor' => 'ops@example.com',
        ])
            ->expectsOutputToContain('Circular successor chain')
            ->assertExitCode(1);

        // AAA must remain unchanged (no successor pinned).
        self::assertNull(DhlProductModel::query()->whereKey('AAA')->first()->replaced_by_code);
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
