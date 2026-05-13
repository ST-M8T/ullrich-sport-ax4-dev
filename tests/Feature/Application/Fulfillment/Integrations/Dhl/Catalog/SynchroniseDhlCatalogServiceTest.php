<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Fulfillment\Integrations\Dhl\Catalog;

use App\Application\Fulfillment\Integrations\Dhl\Catalog\DhlCatalogBootstrapper;
use App\Application\Fulfillment\Integrations\Dhl\Catalog\SynchroniseDhlCatalogCommand;
use App\Application\Fulfillment\Integrations\Dhl\Catalog\SynchroniseDhlCatalogService;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlAdditionalServiceRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlProductRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlProductServiceAssignmentRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\AuditActor;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\CountryCode;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlCatalogSource;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlMarketAvailability;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DimensionLimits;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\WeightLimits;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPackageType;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProduct;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlCatalogAuditLogModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Feature tests for the DHL catalog sync orchestrator (PROJ-2, t12).
 *
 * The {@see DhlCatalogBootstrapper} is mocked so no live API call happens —
 * Engineering-Handbuch §34 (external services kept behind a port).
 */
final class SynchroniseDhlCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    private DhlProductRepository $productRepo;

    private DhlAdditionalServiceRepository $serviceRepo;

    private DhlProductServiceAssignmentRepository $assignmentRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepo = $this->app->make(DhlProductRepository::class);
        $this->serviceRepo = $this->app->make(DhlAdditionalServiceRepository::class);
        $this->assignmentRepo = $this->app->make(DhlProductServiceAssignmentRepository::class);

        // Keep shrinkage detection deterministic.
        config()->set('dhl-catalog.suspicious_shrinkage_threshold', 0.10);
        config()->set('dhl-catalog.default_countries', ['DE']);
        config()->set('dhl-catalog.default_payer_codes', ['DAP']);
        config()->set('dhl-catalog.alert_recipients', ['ops@example.test']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_creates_products_services_and_assignments_on_first_sync(): void
    {
        $this->bindBootstrapper([
            'products' => [$this->productRow('ECI')],
            'services' => [$this->serviceRow('NOT')],
            'assignments' => [$this->assignmentRow('ECI', 'NOT')],
            'errors' => [],
        ]);

        $service = $this->app->make(SynchroniseDhlCatalogService::class);
        $result = $service->execute(new SynchroniseDhlCatalogCommand);

        self::assertFalse($result->hasErrors(), 'no errors expected: '.json_encode($result->errors));
        self::assertSame(1, $result->productsAdded);
        self::assertSame(1, $result->servicesAdded);
        self::assertSame(1, $result->assignmentsAdded);
        self::assertSame(0, $result->productsUpdated);
        self::assertNotNull($this->productRepo->findByCode(new DhlProductCode('ECI')));
        self::assertNotNull($this->serviceRepo->findByCode('NOT'));
    }

    public function test_updates_product_when_name_changes_and_writes_audit_diff(): void
    {
        $this->bindBootstrapper([
            'products' => [$this->productRow('ECI', name: 'Old Name')],
            'services' => [],
            'assignments' => [],
            'errors' => [],
        ]);
        $service = $this->app->make(SynchroniseDhlCatalogService::class);
        $service->execute(new SynchroniseDhlCatalogCommand);

        // Second pass with a changed name.
        $this->bindBootstrapper([
            'products' => [$this->productRow('ECI', name: 'New Name')],
            'services' => [],
            'assignments' => [],
            'errors' => [],
        ]);
        $service = $this->app->make(SynchroniseDhlCatalogService::class);
        $result = $service->execute(new SynchroniseDhlCatalogCommand);

        self::assertFalse($result->hasErrors());
        self::assertSame(1, $result->productsUpdated);

        $auditRow = DhlCatalogAuditLogModel::query()
            ->where('entity_type', 'product')
            ->where('entity_key', 'ECI')
            ->where('action', 'updated')
            ->where('actor', 'system:dhl-sync')
            ->first();
        self::assertNotNull($auditRow, 'expected an audit row for the update');
        self::assertSame('Old Name', $auditRow->diff['before']['name']);
        self::assertSame('New Name', $auditRow->diff['after']['name']);
    }

    public function test_deprecates_products_missing_from_api(): void
    {
        $this->bindBootstrapper([
            'products' => [$this->productRow('A'), $this->productRow('B')],
            'services' => [],
            'assignments' => [],
            'errors' => [],
        ]);
        $service = $this->app->make(SynchroniseDhlCatalogService::class);
        $service->execute(new SynchroniseDhlCatalogCommand);

        // Second run: B disappears. Threshold 0.10 of 2 = ceil(0.2)=1 ⇒ 1 is allowed.
        $this->bindBootstrapper([
            'products' => [$this->productRow('A')],
            'services' => [],
            'assignments' => [],
            'errors' => [],
        ]);
        $service = $this->app->make(SynchroniseDhlCatalogService::class);
        $result = $service->execute(new SynchroniseDhlCatalogCommand);

        self::assertFalse($result->suspicious);
        self::assertSame(1, $result->productsDeprecated);
        $loaded = $this->productRepo->findByCode(new DhlProductCode('B'));
        self::assertNotNull($loaded);
        self::assertTrue($loaded->isDeprecated());
    }

    public function test_restores_previously_deprecated_product(): void
    {
        // Seed a deprecated product directly via the repo.
        $product = $this->makeDomainProduct('R1');
        $this->productRepo->save($product, AuditActor::system('seed'));
        $this->productRepo->softDeprecate(new DhlProductCode('R1'), null, AuditActor::system('seed'));

        $this->bindBootstrapper([
            'products' => [$this->productRow('R1')],
            'services' => [],
            'assignments' => [],
            'errors' => [],
        ]);
        $service = $this->app->make(SynchroniseDhlCatalogService::class);
        $result = $service->execute(new SynchroniseDhlCatalogCommand);

        self::assertSame(1, $result->productsRestored);
        $loaded = $this->productRepo->findByCode(new DhlProductCode('R1'));
        self::assertNotNull($loaded);
        self::assertFalse($loaded->isDeprecated());
    }

    public function test_no_changes_on_identical_rerun(): void
    {
        $this->bindBootstrapper([
            'products' => [$this->productRow('SAME')],
            'services' => [],
            'assignments' => [],
            'errors' => [],
        ]);
        $service = $this->app->make(SynchroniseDhlCatalogService::class);
        $service->execute(new SynchroniseDhlCatalogCommand);

        $this->bindBootstrapper([
            'products' => [$this->productRow('SAME')],
            'services' => [],
            'assignments' => [],
            'errors' => [],
        ]);
        $service = $this->app->make(SynchroniseDhlCatalogService::class);
        $result = $service->execute(new SynchroniseDhlCatalogCommand);

        self::assertSame(0, $result->totalChanges(), 'identical rerun is a no-op');
    }

    public function test_suspicious_shrinkage_blocks_writes(): void
    {
        // Seed 20 products.
        $rows = [];
        for ($i = 1; $i <= 20; $i++) {
            $rows[] = $this->productRow('P'.$i);
        }
        $this->bindBootstrapper([
            'products' => $rows,
            'services' => [],
            'assignments' => [],
            'errors' => [],
        ]);
        $service = $this->app->make(SynchroniseDhlCatalogService::class);
        $service->execute(new SynchroniseDhlCatalogCommand);

        $beforeCount = $this->countActiveProducts();
        self::assertSame(20, $beforeCount);

        // Second pass with only 1 row — below ceil(0.10 * 20) = 2.
        $this->bindBootstrapper([
            'products' => [$this->productRow('P1')],
            'services' => [],
            'assignments' => [],
            'errors' => [],
        ]);
        $service = $this->app->make(SynchroniseDhlCatalogService::class);
        $result = $service->execute(new SynchroniseDhlCatalogCommand);

        self::assertTrue($result->suspicious, 'expected suspicious=true');
        self::assertSame(0, $result->productsDeprecated, 'no deprecations on shrinkage');
        self::assertSame(0, $result->productsUpdated);
        self::assertSame(20, $this->countActiveProducts(), 'product count unchanged');

        $codes = array_column($result->errors, 'code');
        self::assertContains(SynchroniseDhlCatalogService::ERROR_SUSPICIOUS_SHRINKAGE, $codes);
    }

    public function test_dry_run_does_not_persist_changes(): void
    {
        $this->bindBootstrapper([
            'products' => [$this->productRow('DRY')],
            'services' => [],
            'assignments' => [],
            'errors' => [],
        ]);
        $service = $this->app->make(SynchroniseDhlCatalogService::class);
        $result = $service->execute(new SynchroniseDhlCatalogCommand(dryRun: true));

        self::assertTrue($result->dryRun);
        self::assertNull(
            $this->productRepo->findByCode(new DhlProductCode('DRY')),
            'dry run must not persist'
        );
    }

    public function test_audit_actor_defaults_to_system_dhl_sync(): void
    {
        $this->bindBootstrapper([
            'products' => [$this->productRow('AC1')],
            'services' => [],
            'assignments' => [],
            'errors' => [],
        ]);
        $service = $this->app->make(SynchroniseDhlCatalogService::class);
        $service->execute(new SynchroniseDhlCatalogCommand);

        $count = DhlCatalogAuditLogModel::query()
            ->where('entity_type', 'product')
            ->where('entity_key', 'AC1')
            ->where('actor', 'system:dhl-sync')
            ->count();
        self::assertSame(1, $count);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * @param  array{products:list<array<string,mixed>>,services:list<array<string,mixed>>,assignments:list<array<string,mixed>>,errors:list<array<string,mixed>>}  $dataset
     */
    private function bindBootstrapper(array $dataset): void
    {
        $mock = Mockery::mock(DhlCatalogBootstrapper::class);
        $mock->shouldReceive('bootstrap')
            ->andReturn([
                'products' => $dataset['products'],
                'services' => $dataset['services'],
                'assignments' => $dataset['assignments'],
                'errors' => $dataset['errors'],
                'counts' => [],
            ]);
        $this->app->instance(DhlCatalogBootstrapper::class, $mock);
        // Refresh the service so it picks up the new bootstrapper instance.
        $this->app->forgetInstance(SynchroniseDhlCatalogService::class);
    }

    /**
     * @return array<string,mixed>
     */
    private function productRow(string $code, string $name = 'Product '.'X'): array
    {
        if ($name === 'Product X') {
            $name = 'Product '.$code;
        }

        return [
            'code' => $code,
            'name' => $name,
            'description' => 'desc',
            'market_availability' => 'B2B',
            'from_countries' => ['DE'],
            'to_countries' => ['AT'],
            'allowed_package_types' => ['PLT'],
            'weight_min_kg' => 0.0,
            'weight_max_kg' => 1000.0,
            'dim_max_l_cm' => 240.0,
            'dim_max_b_cm' => 120.0,
            'dim_max_h_cm' => 180.0,
            'valid_from' => '2024-01-01T00:00:00Z',
            'source' => 'api',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serviceRow(string $code): array
    {
        return [
            'code' => $code,
            'name' => 'Service '.$code,
            'description' => '',
            'category' => 'notification',
            'parameter_schema' => ['type' => 'object'],
            'source' => 'api',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function assignmentRow(string $product, string $service): array
    {
        return [
            'product_code' => $product,
            'service_code' => $service,
            'from_country' => 'DE',
            'to_country' => 'AT',
            'payer_code' => 'DAP',
            'requirement' => 'allowed',
            'default_parameters' => [],
            'source' => 'api',
        ];
    }

    private function makeDomainProduct(string $code): DhlProduct
    {
        return new DhlProduct(
            code: new DhlProductCode($code),
            name: 'Product '.$code,
            description: 'desc',
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

    private function countActiveProducts(): int
    {
        $n = 0;
        foreach ($this->productRepo->findAllActive(new DateTimeImmutable) as $_) {
            $n++;
        }

        return $n;
    }
}
