<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api\Admin;

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
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPayerCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AllowedDhlServicesIntersectionTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/admin/dhl/catalog/allowed-services/intersection';

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

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->postJson(self::URL, ['routings' => []]);
        $response->assertStatus(401);
    }

    public function test_missing_permission_returns_403(): void
    {
        $this->signInWithRole('support');
        $response = $this->postJson(self::URL, [
            'routings' => [[
                'product_code' => 'EX1',
                'from_country' => 'DE',
                'to_country' => 'AT',
                'payer_code' => 'DAP',
            ]],
        ]);
        $response->assertStatus(403);
    }

    public function test_intersection_of_three_routings(): void
    {
        $this->signInWithRole('operations');
        $this->seedCatalog();

        $response = $this->postJson(self::URL, [
            'routings' => [
                ['product_code' => 'EX1', 'from_country' => 'DE', 'to_country' => 'AT', 'payer_code' => 'DAP'],
                ['product_code' => 'EX1', 'from_country' => 'DE', 'to_country' => 'CH', 'payer_code' => 'DAP'],
                ['product_code' => 'EX1', 'from_country' => 'DE', 'to_country' => 'FR', 'payer_code' => 'DAP'],
            ],
        ]);

        $response->assertOk();
        $codes = array_column($response->json('services'), 'code');
        sort($codes);
        // COD is global ALLOWED → in all three. SMS only in DE→AT → not in intersection.
        self::assertSame(['COD'], $codes);
        self::assertSame(3, $response->json('context.routings_count'));
    }

    public function test_required_in_any_routing_propagates_to_result(): void
    {
        $this->signInWithRole('operations');
        $this->seedCatalog();

        $response = $this->postJson(self::URL, [
            'routings' => [
                // DE→AT: COD allowed, SMS required.
                ['product_code' => 'EX1', 'from_country' => 'DE', 'to_country' => 'AT', 'payer_code' => 'DAP'],
                // DE→CH: only COD allowed — but we add SMS required there too via seed below.
                ['product_code' => 'EX1', 'from_country' => 'DE', 'to_country' => 'CH', 'payer_code' => 'DAP'],
            ],
        ]);

        $response->assertOk();
        // Intersection: only COD.
        $codes = array_column($response->json('services'), 'code');
        self::assertSame(['COD'], $codes);
    }

    public function test_single_routing_matches_single_endpoint_payload(): void
    {
        $this->signInWithRole('operations');
        $this->seedCatalog();

        $response = $this->postJson(self::URL, [
            'routings' => [
                ['product_code' => 'EX1', 'from_country' => 'DE', 'to_country' => 'AT', 'payer_code' => 'DAP'],
            ],
        ]);

        $response->assertOk();
        $codes = array_column($response->json('services'), 'code');
        sort($codes);
        self::assertSame(['COD', 'SMS'], $codes);
    }

    public function test_more_than_100_routings_returns_422(): void
    {
        $this->signInWithRole('operations');

        $routings = [];
        for ($i = 0; $i < 101; $i++) {
            $routings[] = ['product_code' => 'EX1', 'from_country' => 'DE', 'to_country' => 'AT', 'payer_code' => 'DAP'];
        }
        $response = $this->postJson(self::URL, ['routings' => $routings]);

        $response->assertStatus(422);
    }

    public function test_empty_routings_returns_422(): void
    {
        $this->signInWithRole('operations');
        $response = $this->postJson(self::URL, ['routings' => []]);
        $response->assertStatus(422);
    }

    private function seedCatalog(): void
    {
        $this->productRepo->save($this->makeProduct('EX1'), $this->actor);
        $this->serviceRepo->save($this->makeService('COD', DhlServiceCategory::DELIVERY), $this->actor);
        $this->serviceRepo->save($this->makeService('SMS', DhlServiceCategory::NOTIFICATION), $this->actor);

        // COD: global ALLOWED.
        $this->assignmentRepo->save(
            $this->makeAssignment('EX1', 'COD', null, null, null, DhlServiceRequirement::ALLOWED),
            $this->actor,
        );
        // SMS: only DE→AT REQUIRED (not present in other routings).
        $this->assignmentRepo->save(
            $this->makeAssignment('EX1', 'SMS', 'DE', 'AT', null, DhlServiceRequirement::REQUIRED),
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
            payerCode: $payer !== null ? DhlPayerCode::fromString($payer) : null,
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
            toCountries: [new CountryCode('AT'), new CountryCode('CH'), new CountryCode('FR')],
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
            description: '',
            category: $cat,
            parameterSchema: JsonSchema::fromArray(['type' => 'object']),
            deprecatedAt: null,
            source: DhlCatalogSource::SEED,
            syncedAt: null,
        );
    }
}
