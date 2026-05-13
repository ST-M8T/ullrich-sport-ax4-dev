<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api\Admin;

use App\Application\Fulfillment\Integrations\Dhl\Catalog\Queries\GetAllowedDhlServices;
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
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class AllowedDhlServicesControllerTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/admin/dhl/catalog/allowed-services';

    private DhlProductRepository $productRepo;
    private DhlAdditionalServiceRepository $serviceRepo;
    private DhlProductServiceAssignmentRepository $assignmentRepo;
    private AuditActor $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productRepo = $this->app->make(DhlProductRepository::class);
        $this->serviceRepo = $this->app->make(DhlAdditionalServiceRepository::class);
        $this->assignmentRepo = $this->app->make(DhlProductServiceAssignmentRepository::class);
        $this->actor = AuditActor::system('test');
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson(self::URL . '?product_code=EX1&from_country=DE&to_country=AT&payer_code=DAP');

        $response->assertStatus(401);
    }

    public function test_user_without_permission_receives_403(): void
    {
        $this->signInWithRole('support');

        $response = $this->getJson(self::URL . '?product_code=EX1&from_country=DE&to_country=AT&payer_code=DAP');

        $response->assertStatus(403);
    }

    public function test_returns_allowed_services_without_forbidden(): void
    {
        $this->signInWithRole('operations');
        $this->seedCatalog();

        $response = $this->getJson(self::URL . '?product_code=EX1&from_country=DE&to_country=AT&payer_code=DAP');

        $response->assertOk();
        $response->assertJsonPath('context.product_code', 'EX1');
        $response->assertJsonPath('context.from_country', 'DE');
        $response->assertJsonPath('context.to_country', 'AT');
        $response->assertJsonPath('context.payer_code', 'DAP');

        $codes = array_column($response->json('services'), 'code');
        sort($codes);
        // COD allowed, SMS required, NOT forbidden → only COD + SMS visible.
        self::assertSame(['COD', 'SMS'], $codes);

        // Required service must come first.
        self::assertSame('SMS', $response->json('services.0.code'));
        self::assertSame('required', $response->json('services.0.requirement'));
        self::assertSame('notification', $response->json('services.0.category'));
        self::assertIsArray($response->json('services.0.parameter_schema'));
    }

    public function test_response_is_cached_on_second_call(): void
    {
        $this->signInWithRole('operations');
        $this->seedCatalog();

        $url = self::URL . '?product_code=EX1&from_country=DE&to_country=AT&payer_code=DAP';

        // Warm cache.
        $this->getJson($url)->assertOk();

        // Delete underlying data — cached response must still be returned.
        \App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlProductServiceAssignmentModel::query()->delete();

        $response = $this->getJson($url);
        $response->assertOk();
        self::assertCount(2, $response->json('services'));
    }

    public function test_cache_flush_after_sync_returns_fresh_data(): void
    {
        $this->signInWithRole('operations');
        $this->seedCatalog();

        $url = self::URL . '?product_code=EX1&from_country=DE&to_country=AT&payer_code=DAP';

        $this->getJson($url)->assertOk();

        Cache::tags([GetAllowedDhlServices::CACHE_TAG])->flush();
        \App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlProductServiceAssignmentModel::query()->delete();

        $response = $this->getJson($url);
        $response->assertOk();
        self::assertCount(0, $response->json('services'));
    }

    public function test_invalid_country_returns_422(): void
    {
        $this->signInWithRole('operations');

        $response = $this->getJson(self::URL . '?product_code=EX1&from_country=DEU&to_country=AT&payer_code=DAP');

        $response->assertStatus(422);
    }

    public function test_invalid_payer_returns_422(): void
    {
        $this->signInWithRole('operations');

        $response = $this->getJson(self::URL . '?product_code=EX1&from_country=DE&to_country=AT&payer_code=SENDER');

        $response->assertStatus(422);
    }

    public function test_missing_required_param_returns_422(): void
    {
        $this->signInWithRole('operations');

        $response = $this->getJson(self::URL . '?product_code=EX1&from_country=DE&to_country=AT');

        $response->assertStatus(422);
    }

    private function seedCatalog(): void
    {
        $this->productRepo->save($this->makeProduct('EX1'), $this->actor);
        $this->serviceRepo->save($this->makeService('COD', DhlServiceCategory::DELIVERY), $this->actor);
        $this->serviceRepo->save($this->makeService('SMS', DhlServiceCategory::NOTIFICATION), $this->actor);
        $this->serviceRepo->save($this->makeService('NOT', DhlServiceCategory::SPECIAL), $this->actor);

        $this->assignmentRepo->save(
            $this->makeAssignment('EX1', 'COD', null, null, null, DhlServiceRequirement::ALLOWED),
            $this->actor,
        );
        $this->assignmentRepo->save(
            $this->makeAssignment('EX1', 'SMS', 'DE', 'AT', null, DhlServiceRequirement::REQUIRED),
            $this->actor,
        );
        $this->assignmentRepo->save(
            $this->makeAssignment('EX1', 'NOT', 'DE', 'AT', null, DhlServiceRequirement::FORBIDDEN),
            $this->actor,
        );
    }

    private function makeAssignment(
        string $product,
        string $service,
        ?string $from,
        ?string $to,
        ?string $payer,
        DhlServiceRequirement $req,
    ): DhlProductServiceAssignment {
        return new DhlProductServiceAssignment(
            productCode: new DhlProductCode($product),
            serviceCode: $service,
            fromCountry: $from !== null ? new CountryCode($from) : null,
            toCountry: $to !== null ? new CountryCode($to) : null,
            payerCode: $payer !== null ? \App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPayerCode::fromString($payer) : null,
            requirement: $req,
            defaultParameters: [],
            source: DhlCatalogSource::SEED,
            syncedAt: null,
        );
    }

    private function makeProduct(string $code): DhlProduct
    {
        return new DhlProduct(
            code: new DhlProductCode($code),
            name: 'P ' . $code,
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

    private function makeService(string $code, DhlServiceCategory $cat): DhlAdditionalService
    {
        return new DhlAdditionalService(
            code: $code,
            name: 'Service ' . $code,
            description: 'desc-' . $code,
            category: $cat,
            parameterSchema: JsonSchema::fromArray(['type' => 'object']),
            deprecatedAt: null,
            source: DhlCatalogSource::SEED,
            syncedAt: null,
        );
    }
}
