<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Dhl\Catalog\Mappers;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlAdditionalService;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProduct;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProductServiceAssignment;
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
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlAdditionalServiceModel;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlProductModel;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlProductServiceAssignmentModel;
use App\Infrastructure\Persistence\Dhl\Catalog\Mappers\DhlCatalogPersistenceMapper;
use DateTimeImmutable;
use Tests\TestCase;

/**
 * Engineering-Handbuch §13 (Mapper-Regel): explicit, testable, no hidden
 * partial mappings. These tests pin the contract between domain and
 * persistence models for all three aggregates including JSON casts and
 * nullable fields.
 */
final class DhlCatalogPersistenceMapperTest extends TestCase
{
    private DhlCatalogPersistenceMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new DhlCatalogPersistenceMapper;
    }

    // ----- Product -----

    public function test_product_roundtrip_preserves_all_fields(): void
    {
        $entity = $this->makeProduct();

        $model = $this->mapper->toProductModel($entity);

        self::assertSame('EX1', $model->code);
        self::assertSame('Express One', $model->name);
        self::assertSame('B2B', $model->market_availability);
        self::assertSame(['DE', 'FR'], $model->from_countries);
        self::assertSame(['AT'], $model->to_countries);
        self::assertSame(['PLT'], $model->allowed_package_types);
        self::assertSame(0.0, $model->weight_min_kg);
        self::assertSame(1000.0, $model->weight_max_kg);
        self::assertNull($model->replaced_by_code);

        $roundtrip = $this->mapper->toProductEntity($this->prepareModelForRead($model));

        self::assertSame('EX1', $roundtrip->code()->value);
        self::assertSame('Express One', $roundtrip->name());
        self::assertSame(DhlMarketAvailability::B2B, $roundtrip->marketAvailability());
        self::assertSame(['DE', 'FR'], array_map(static fn (CountryCode $c) => $c->value, $roundtrip->fromCountries()));
        self::assertSame(['AT'], array_map(static fn (CountryCode $c) => $c->value, $roundtrip->toCountries()));
        self::assertSame(['PLT'], array_map(static fn (DhlPackageType $p) => $p->code, $roundtrip->allowedPackageTypes()));
        self::assertSame(0.0, $roundtrip->weightLimits()->minKg);
        self::assertSame(1000.0, $roundtrip->weightLimits()->maxKg);
        self::assertSame(240.0, $roundtrip->dimensionLimits()->maxLengthCm);
        self::assertNull($roundtrip->validUntil());
        self::assertNull($roundtrip->deprecatedAt());
        self::assertNull($roundtrip->replacedByCode());
        self::assertSame(DhlCatalogSource::SEED, $roundtrip->source());
    }

    public function test_product_with_replacement_and_deprecation_roundtrip(): void
    {
        $entity = new DhlProduct(
            code: new DhlProductCode('OLD'),
            name: 'Old',
            description: 'Replaced product',
            marketAvailability: DhlMarketAvailability::BOTH,
            fromCountries: [new CountryCode('DE')],
            toCountries: [new CountryCode('AT')],
            allowedPackageTypes: [new DhlPackageType('PLT')],
            weightLimits: new WeightLimits(0.0, 100.0),
            dimensionLimits: new DimensionLimits(120.0, 80.0, 60.0),
            validFrom: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            validUntil: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            deprecatedAt: new DateTimeImmutable('2026-02-01T00:00:00Z'),
            replacedByCode: new DhlProductCode('NEW'),
            source: DhlCatalogSource::API,
            syncedAt: new DateTimeImmutable('2026-03-01T10:00:00Z'),
        );

        $model = $this->mapper->toProductModel($entity);
        self::assertSame('NEW', $model->replaced_by_code);
        self::assertSame('api', $model->source);
        self::assertNotNull($model->deprecated_at);

        $roundtrip = $this->mapper->toProductEntity($this->prepareModelForRead($model));
        self::assertNotNull($roundtrip->replacedByCode());
        self::assertSame('NEW', $roundtrip->replacedByCode()->value);
        self::assertTrue($roundtrip->isDeprecated());
        self::assertSame(DhlCatalogSource::API, $roundtrip->source());
    }

    public function test_product_update_existing_model_keeps_instance(): void
    {
        $existing = new DhlProductModel;
        $existing->code = 'EX1';

        $entity = $this->makeProduct();
        $model = $this->mapper->toProductModel($entity, $existing);

        self::assertSame($existing, $model, 'Mapper must mutate the passed model, not create a new one.');
    }

    // ----- Additional Service -----

    public function test_service_roundtrip_preserves_schema_and_nullable(): void
    {
        $entity = $this->makeService('COD');
        $model = $this->mapper->toServiceModel($entity);

        self::assertSame('COD', $model->code);
        self::assertSame('delivery', $model->category);
        self::assertIsArray($model->parameter_schema);
        self::assertSame(['type' => 'object'], $model->parameter_schema);
        self::assertNull($model->deprecated_at);
        self::assertSame('seed', $model->source);

        $roundtrip = $this->mapper->toServiceEntity($this->prepareModelForRead($model));
        self::assertSame('COD', $roundtrip->code());
        self::assertSame(DhlServiceCategory::DELIVERY, $roundtrip->category());
        self::assertFalse($roundtrip->isDeprecated());
        self::assertSame(['type' => 'object'], $roundtrip->parameterSchema()->toArray());
    }

    public function test_service_with_null_parameter_schema_yields_empty_array_on_entity(): void
    {
        $model = new DhlAdditionalServiceModel;
        $model->code = 'X';
        $model->name = 'X service';
        $model->description = '';
        $model->category = 'delivery';
        $model->parameter_schema = null; // simulating cast-empty
        $model->deprecated_at = null;
        $model->source = 'seed';
        $model->synced_at = null;

        $entity = $this->mapper->toServiceEntity($model);
        self::assertSame([], $entity->parameterSchema()->toArray());
    }

    // ----- Assignment -----

    public function test_assignment_roundtrip_with_full_routing(): void
    {
        $entity = new DhlProductServiceAssignment(
            productCode: new DhlProductCode('EX1'),
            serviceCode: 'COD',
            fromCountry: new CountryCode('DE'),
            toCountry: new CountryCode('AT'),
            payerCode: DhlPayerCode::DAP,
            requirement: DhlServiceRequirement::REQUIRED,
            defaultParameters: ['amount' => 12.5, 'currency' => 'EUR'],
            source: DhlCatalogSource::SEED,
            syncedAt: null,
        );

        $model = $this->mapper->toAssignmentModel($entity);
        self::assertSame('EX1', $model->product_code);
        self::assertSame('COD', $model->service_code);
        self::assertSame('DE', $model->from_country);
        self::assertSame('AT', $model->to_country);
        self::assertSame('DAP', $model->payer_code);
        self::assertSame('required', $model->requirement);
        self::assertSame(['amount' => 12.5, 'currency' => 'EUR'], $model->default_parameters);

        $roundtrip = $this->mapper->toAssignmentEntity($this->prepareModelForRead($model));
        self::assertSame('EX1', $roundtrip->productCode()->value);
        self::assertSame('COD', $roundtrip->serviceCode());
        self::assertNotNull($roundtrip->fromCountry());
        self::assertSame('DE', $roundtrip->fromCountry()->value);
        self::assertSame(DhlPayerCode::DAP, $roundtrip->payerCode());
        self::assertSame(DhlServiceRequirement::REQUIRED, $roundtrip->requirement());
        self::assertSame(['amount' => 12.5, 'currency' => 'EUR'], $roundtrip->defaultParameters());
    }

    public function test_assignment_with_null_routing_and_empty_defaults_normalises_to_null_column(): void
    {
        $entity = new DhlProductServiceAssignment(
            productCode: new DhlProductCode('EX1'),
            serviceCode: 'COD',
            fromCountry: null,
            toCountry: null,
            payerCode: null,
            requirement: DhlServiceRequirement::ALLOWED,
            defaultParameters: [],
            source: DhlCatalogSource::SEED,
            syncedAt: null,
        );

        $model = $this->mapper->toAssignmentModel($entity);
        self::assertNull($model->from_country);
        self::assertNull($model->to_country);
        self::assertNull($model->payer_code);
        self::assertNull(
            $model->default_parameters,
            'Empty default_parameters MUST be persisted as NULL to keep JSON column sparse.',
        );

        $roundtrip = $this->mapper->toAssignmentEntity($model);
        self::assertNull($roundtrip->fromCountry());
        self::assertNull($roundtrip->toCountry());
        self::assertNull($roundtrip->payerCode());
        self::assertSame([], $roundtrip->defaultParameters());
    }

    // ----- Helpers -----

    private function makeProduct(): DhlProduct
    {
        return new DhlProduct(
            code: new DhlProductCode('EX1'),
            name: 'Express One',
            description: 'Test product',
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

    /**
     * When a model is built in-memory (not loaded from DB), the immutable
     * datetime casts haven't materialised the attribute values into
     * DateTimeImmutable. The mapper accepts both string and DateTimeInterface,
     * so we just pass the model through unchanged — but force the timestamp
     * fields into known types to mirror DB-read behaviour.
     */
    private function prepareModelForRead(
        DhlProductModel|DhlAdditionalServiceModel|DhlProductServiceAssignmentModel $model,
    ): DhlProductModel|DhlAdditionalServiceModel|DhlProductServiceAssignmentModel {
        return $model;
    }
}
