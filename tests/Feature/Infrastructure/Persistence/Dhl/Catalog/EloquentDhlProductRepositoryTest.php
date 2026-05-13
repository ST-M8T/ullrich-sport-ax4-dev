<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Persistence\Dhl\Catalog;

use App\Application\Fulfillment\Integrations\Dhl\Catalog\DhlCatalogAuditLogger;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlAdditionalService;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProduct;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProductServiceAssignment;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlAdditionalServiceRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlProductRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlProductServiceAssignmentRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\AuditActor;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\CountryCode;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlCatalogSource;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlMarketAvailability;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlServiceCategory;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlServiceRequirement;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DimensionLimits;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\JsonSchema;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\WeightLimits;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPackageType;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlCatalogAuditLogModel;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlProductServiceAssignmentModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentDhlProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private DhlProductRepository $repository;
    private AuditActor $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->app->make(DhlProductRepository::class);
        $this->actor = AuditActor::system('test');
    }

    public function test_save_and_find_by_code_roundtrip(): void
    {
        $product = $this->makeProduct('EX1');
        $this->repository->save($product, $this->actor);

        $loaded = $this->repository->findByCode(new DhlProductCode('EX1'));
        self::assertNotNull($loaded);
        self::assertSame('EX1', $loaded->code()->value);
        self::assertSame('Express One', $loaded->name());
        self::assertSame(DhlMarketAvailability::B2B, $loaded->marketAvailability());
        self::assertCount(1, $loaded->fromCountries());
        self::assertSame('DE', $loaded->fromCountries()[0]->value);
    }

    public function test_save_creates_audit_log_entry(): void
    {
        $product = $this->makeProduct('EX2');
        $this->repository->save($product, $this->actor);

        $rows = DhlCatalogAuditLogModel::query()
            ->where('entity_type', DhlCatalogAuditLogger::ENTITY_PRODUCT)
            ->where('entity_key', 'EX2')
            ->get();

        self::assertCount(1, $rows);
        self::assertSame(DhlCatalogAuditLogger::ACTION_CREATED, $rows[0]->action);
        self::assertSame('system:test', $rows[0]->actor);
        $diff = $rows[0]->diff;
        self::assertNull($diff['before']);
        self::assertSame('EX2', $diff['after']['code']);
        self::assertArrayHasKey('changed', $diff);
    }

    public function test_update_creates_audit_log_with_diff(): void
    {
        $product = $this->makeProduct('EX3', name: 'Initial Name');
        $this->repository->save($product, $this->actor);

        $updated = $this->makeProduct('EX3', name: 'Updated Name');
        $this->repository->save($updated, $this->actor);

        $rows = DhlCatalogAuditLogModel::query()
            ->where('entity_key', 'EX3')
            ->where('action', DhlCatalogAuditLogger::ACTION_UPDATED)
            ->get();

        self::assertCount(1, $rows);
        $changed = $rows[0]->diff['changed'];
        self::assertArrayHasKey('name', $changed);
        self::assertSame('Initial Name', $changed['name']['before']);
        self::assertSame('Updated Name', $changed['name']['after']);
    }

    public function test_update_with_no_changes_writes_no_audit_row(): void
    {
        $product = $this->makeProduct('EX4');
        $this->repository->save($product, $this->actor);

        // Save same entity again — diff should be empty → no audit row.
        $same = $this->makeProduct('EX4');
        $this->repository->save($same, $this->actor);

        $updateRows = DhlCatalogAuditLogModel::query()
            ->where('entity_key', 'EX4')
            ->where('action', DhlCatalogAuditLogger::ACTION_UPDATED)
            ->count();

        self::assertSame(0, $updateRows);
    }

    public function test_soft_deprecate_sets_deprecated_at_and_audits(): void
    {
        $product = $this->makeProduct('EX5');
        $this->repository->save($product, $this->actor);

        $this->repository->softDeprecate(new DhlProductCode('EX5'), null, $this->actor);

        $loaded = $this->repository->findByCode(new DhlProductCode('EX5'));
        self::assertNotNull($loaded);
        self::assertTrue($loaded->isDeprecated());

        $deprecatedAudit = DhlCatalogAuditLogModel::query()
            ->where('entity_key', 'EX5')
            ->where('action', DhlCatalogAuditLogger::ACTION_DEPRECATED)
            ->count();

        self::assertSame(1, $deprecatedAudit);
    }

    public function test_restore_clears_deprecation(): void
    {
        $product = $this->makeProduct('EX6');
        $this->repository->save($product, $this->actor);
        $this->repository->softDeprecate(new DhlProductCode('EX6'), null, $this->actor);

        $this->repository->restore(new DhlProductCode('EX6'), $this->actor);

        $loaded = $this->repository->findByCode(new DhlProductCode('EX6'));
        self::assertNotNull($loaded);
        self::assertFalse($loaded->isDeprecated());

        $restoreAudit = DhlCatalogAuditLogModel::query()
            ->where('entity_key', 'EX6')
            ->where('action', DhlCatalogAuditLogger::ACTION_RESTORED)
            ->count();

        self::assertSame(1, $restoreAudit);
    }

    public function test_exists_by_code(): void
    {
        self::assertFalse($this->repository->existsByCode(new DhlProductCode('NO')));

        $this->repository->save($this->makeProduct('EX7'), $this->actor);

        self::assertTrue($this->repository->existsByCode(new DhlProductCode('EX7')));
    }

    public function test_find_deprecated_since_window(): void
    {
        $this->repository->save($this->makeProduct('EX8'), $this->actor);
        $this->repository->softDeprecate(new DhlProductCode('EX8'), null, $this->actor);

        $deprecated = iterator_to_array(
            $this->repository->findDeprecatedSince(new DateTimeImmutable('-1 hour')),
            false,
        );

        self::assertCount(1, $deprecated);
        self::assertSame('EX8', $deprecated[0]->code()->value);
    }

    public function test_soft_deprecating_product_does_not_cascade_to_assignments(): void
    {
        // Arrange: product + service + two assignments
        $this->repository->save($this->makeProduct('CSC'), $this->actor);

        /** @var DhlAdditionalServiceRepository $serviceRepo */
        $serviceRepo = $this->app->make(DhlAdditionalServiceRepository::class);
        $serviceRepo->save($this->makeService('COD'), $this->actor);
        $serviceRepo->save($this->makeService('SMS'), $this->actor);

        /** @var DhlProductServiceAssignmentRepository $assignRepo */
        $assignRepo = $this->app->make(DhlProductServiceAssignmentRepository::class);
        $assignRepo->save(
            $this->makeAssignment('CSC', 'COD', DhlServiceRequirement::ALLOWED),
            $this->actor,
        );
        $assignRepo->save(
            $this->makeAssignment('CSC', 'SMS', DhlServiceRequirement::ALLOWED),
            $this->actor,
        );

        self::assertSame(
            2,
            DhlProductServiceAssignmentModel::query()->where('product_code', 'CSC')->count(),
            'Precondition: both assignments must exist.',
        );

        // Act: soft-deprecate the product (NOT a hard delete)
        $this->repository->softDeprecate(new DhlProductCode('CSC'), null, $this->actor);

        // Assert: assignments still exist — soft deprecation must not cascade
        self::assertSame(
            2,
            DhlProductServiceAssignmentModel::query()->where('product_code', 'CSC')->count(),
            'Soft deprecation MUST NOT cascade-delete assignments.',
        );
    }

    public function test_restore_writes_audit_action_restored(): void
    {
        $this->repository->save($this->makeProduct('AUD'), $this->actor);
        $this->repository->softDeprecate(new DhlProductCode('AUD'), null, $this->actor);
        $this->repository->restore(new DhlProductCode('AUD'), $this->actor);

        $deprecatedCount = DhlCatalogAuditLogModel::query()
            ->where('entity_type', DhlCatalogAuditLogger::ENTITY_PRODUCT)
            ->where('entity_key', 'AUD')
            ->where('action', DhlCatalogAuditLogger::ACTION_DEPRECATED)
            ->count();

        $restoredCount = DhlCatalogAuditLogModel::query()
            ->where('entity_type', DhlCatalogAuditLogger::ENTITY_PRODUCT)
            ->where('entity_key', 'AUD')
            ->where('action', DhlCatalogAuditLogger::ACTION_RESTORED)
            ->count();

        self::assertSame(1, $deprecatedCount, 'Expect exactly one deprecated audit row.');
        self::assertSame(1, $restoredCount, 'Expect exactly one restored audit row.');
    }

    public function test_find_all_active_excludes_deprecated_and_out_of_window(): void
    {
        $this->repository->save($this->makeProduct('AC1'), $this->actor);
        $this->repository->save($this->makeProduct('AC2'), $this->actor);
        $this->repository->softDeprecate(new DhlProductCode('AC2'), null, $this->actor);

        $active = iterator_to_array(
            $this->repository->findAllActive(new DateTimeImmutable),
            false,
        );

        $codes = array_map(static fn (DhlProduct $p) => $p->code()->value, $active);
        self::assertContains('AC1', $codes);
        self::assertNotContains('AC2', $codes);
    }

    private function makeService(string $code): DhlAdditionalService
    {
        return new DhlAdditionalService(
            code: $code,
            name: 'Service ' . $code,
            description: '',
            category: DhlServiceCategory::DELIVERY,
            parameterSchema: JsonSchema::fromArray(['type' => 'object']),
            deprecatedAt: null,
            source: DhlCatalogSource::SEED,
            syncedAt: null,
        );
    }

    private function makeAssignment(
        string $product,
        string $service,
        DhlServiceRequirement $req,
    ): DhlProductServiceAssignment {
        return new DhlProductServiceAssignment(
            productCode: new DhlProductCode($product),
            serviceCode: $service,
            fromCountry: null,
            toCountry: null,
            payerCode: null,
            requirement: $req,
            defaultParameters: [],
            source: DhlCatalogSource::SEED,
            syncedAt: null,
        );
    }

    private function makeProduct(string $code, string $name = 'Express One'): DhlProduct
    {
        return new DhlProduct(
            code: new DhlProductCode($code),
            name: $name,
            description: 'Test product',
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
