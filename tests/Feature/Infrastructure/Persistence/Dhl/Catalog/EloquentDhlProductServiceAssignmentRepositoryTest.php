<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Persistence\Dhl\Catalog;

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

final class EloquentDhlProductServiceAssignmentRepositoryTest extends TestCase
{
    use RefreshDatabase;

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

        // Seed FK targets
        $this->productRepo->save($this->makeProduct('EX1'), $this->actor);
        $this->serviceRepo->save($this->makeService('COD'), $this->actor);
        $this->serviceRepo->save($this->makeService('SMS'), $this->actor);
    }

    public function test_save_and_find_by_product(): void
    {
        $a = $this->makeAssignment('EX1', 'COD', null, null, null);
        $this->assignmentRepo->save($a, $this->actor);

        $found = iterator_to_array(
            $this->assignmentRepo->findByProduct(new DhlProductCode('EX1')),
            false,
        );

        self::assertCount(1, $found);
        self::assertSame('COD', $found[0]->serviceCode());
    }

    public function test_find_allowed_services_for_global_only(): void
    {
        $a = $this->makeAssignment('EX1', 'COD', null, null, null, DhlServiceRequirement::ALLOWED);
        $this->assignmentRepo->save($a, $this->actor);

        $allowed = iterator_to_array(
            $this->assignmentRepo->findAllowedServicesFor(
                new DhlProductCode('EX1'),
                new CountryCode('DE'),
                new CountryCode('AT'),
                DhlPayerCode::DAP,
            ),
            false,
        );

        self::assertCount(1, $allowed);
        self::assertSame('COD', $allowed[0]->serviceCode());
        self::assertSame(DhlServiceRequirement::ALLOWED, $allowed[0]->requirement());
    }

    public function test_specific_assignment_wins_over_global(): void
    {
        $global = $this->makeAssignment('EX1', 'COD', null, null, null, DhlServiceRequirement::ALLOWED);
        $specific = $this->makeAssignment('EX1', 'COD', 'DE', 'AT', null, DhlServiceRequirement::REQUIRED);
        $this->assignmentRepo->save($global, $this->actor);
        $this->assignmentRepo->save($specific, $this->actor);

        $allowed = iterator_to_array(
            $this->assignmentRepo->findAllowedServicesFor(
                new DhlProductCode('EX1'),
                new CountryCode('DE'),
                new CountryCode('AT'),
                DhlPayerCode::DAP,
            ),
            false,
        );

        self::assertCount(1, $allowed);
        self::assertSame(DhlServiceRequirement::REQUIRED, $allowed[0]->requirement());
    }

    public function test_forbidden_specific_overrides_global_allowed(): void
    {
        $global = $this->makeAssignment('EX1', 'COD', null, null, null, DhlServiceRequirement::ALLOWED);
        $forbidden = $this->makeAssignment('EX1', 'COD', 'DE', 'AT', null, DhlServiceRequirement::FORBIDDEN);
        $this->assignmentRepo->save($global, $this->actor);
        $this->assignmentRepo->save($forbidden, $this->actor);

        $allowed = iterator_to_array(
            $this->assignmentRepo->findAllowedServicesFor(
                new DhlProductCode('EX1'),
                new CountryCode('DE'),
                new CountryCode('AT'),
                DhlPayerCode::DAP,
            ),
            false,
        );

        self::assertCount(1, $allowed);
        self::assertSame(DhlServiceRequirement::FORBIDDEN, $allowed[0]->requirement());
    }

    public function test_non_matching_specific_does_not_apply(): void
    {
        // Specific for FR→AT must not apply to DE→AT.
        $specific = $this->makeAssignment('EX1', 'COD', 'FR', 'AT', null, DhlServiceRequirement::REQUIRED);
        $this->assignmentRepo->save($specific, $this->actor);

        $allowed = iterator_to_array(
            $this->assignmentRepo->findAllowedServicesFor(
                new DhlProductCode('EX1'),
                new CountryCode('DE'),
                new CountryCode('AT'),
                DhlPayerCode::DAP,
            ),
            false,
        );

        self::assertCount(0, $allowed);
    }

    public function test_mixed_services_global_and_specific(): void
    {
        // COD: global ALLOWED + specific REQUIRED → specific wins.
        // SMS: only global ALLOWED → global applies.
        $this->assignmentRepo->save(
            $this->makeAssignment('EX1', 'COD', null, null, null, DhlServiceRequirement::ALLOWED),
            $this->actor,
        );
        $this->assignmentRepo->save(
            $this->makeAssignment('EX1', 'COD', 'DE', 'AT', null, DhlServiceRequirement::REQUIRED),
            $this->actor,
        );
        $this->assignmentRepo->save(
            $this->makeAssignment('EX1', 'SMS', null, null, null, DhlServiceRequirement::ALLOWED),
            $this->actor,
        );

        $allowed = iterator_to_array(
            $this->assignmentRepo->findAllowedServicesFor(
                new DhlProductCode('EX1'),
                new CountryCode('DE'),
                new CountryCode('AT'),
                DhlPayerCode::DAP,
            ),
            false,
        );

        self::assertCount(2, $allowed);
        $byCode = [];
        foreach ($allowed as $a) {
            $byCode[$a->serviceCode()] = $a->requirement();
        }
        self::assertSame(DhlServiceRequirement::REQUIRED, $byCode['COD']);
        self::assertSame(DhlServiceRequirement::ALLOWED, $byCode['SMS']);
    }

    public function test_delete_assignment_removes_row_and_writes_audit(): void
    {
        $a = $this->makeAssignment('EX1', 'COD', null, null, null);
        $this->assignmentRepo->save($a, $this->actor);

        $this->assignmentRepo->delete($a, $this->actor);

        $remaining = iterator_to_array(
            $this->assignmentRepo->findByProduct(new DhlProductCode('EX1')),
            false,
        );
        self::assertCount(0, $remaining);
    }

    private function makeAssignment(
        string $product,
        string $service,
        ?string $from,
        ?string $to,
        ?string $payer,
        DhlServiceRequirement $req = DhlServiceRequirement::ALLOWED,
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
            fromCountries: [new CountryCode('DE'), new CountryCode('FR')],
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
}
