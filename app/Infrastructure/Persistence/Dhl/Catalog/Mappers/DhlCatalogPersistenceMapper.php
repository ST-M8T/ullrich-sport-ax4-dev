<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dhl\Catalog\Mappers;

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
use DateTimeImmutable;

/**
 * Stateless, explicit mapper between Eloquent persistence models and Domain
 * entities. Engineering-Handbuch §13: no automatic spread / hydrate — each
 * field is mapped explicitly so the contract is visible.
 */
final class DhlCatalogPersistenceMapper
{
    // ---------------------------------------------------------------------
    // Product
    // ---------------------------------------------------------------------

    public function toProductEntity(DhlProductModel $m): DhlProduct
    {
        return new DhlProduct(
            code: new DhlProductCode((string) $m->code),
            name: (string) $m->name,
            description: (string) $m->description,
            marketAvailability: DhlMarketAvailability::fromString((string) $m->market_availability),
            fromCountries: $this->toCountryList($m->from_countries),
            toCountries: $this->toCountryList($m->to_countries),
            allowedPackageTypes: $this->toPackageTypeList($m->allowed_package_types),
            weightLimits: new WeightLimits(
                minKg: (float) $m->weight_min_kg,
                maxKg: (float) $m->weight_max_kg,
            ),
            dimensionLimits: new DimensionLimits(
                maxLengthCm: (float) $m->dim_max_l_cm,
                maxWidthCm: (float) $m->dim_max_b_cm,
                maxHeightCm: (float) $m->dim_max_h_cm,
            ),
            validFrom: $this->toImmutable($m->valid_from),
            validUntil: $this->toNullableImmutable($m->valid_until),
            deprecatedAt: $this->toNullableImmutable($m->deprecated_at),
            replacedByCode: $m->replaced_by_code !== null
                ? new DhlProductCode((string) $m->replaced_by_code)
                : null,
            source: DhlCatalogSource::fromString((string) $m->source),
            syncedAt: $this->toNullableImmutable($m->synced_at),
        );
    }

    public function toProductModel(
        DhlProduct $e,
        ?DhlProductModel $existing = null,
    ): DhlProductModel {
        $model = $existing ?? new DhlProductModel;

        $model->code = $e->code()->value;
        $model->name = $e->name();
        $model->description = $e->description();
        $model->market_availability = $e->marketAvailability()->value;
        $model->from_countries = array_map(
            static fn (CountryCode $c): string => $c->value,
            $e->fromCountries(),
        );
        $model->to_countries = array_map(
            static fn (CountryCode $c): string => $c->value,
            $e->toCountries(),
        );
        $model->allowed_package_types = array_map(
            static fn (DhlPackageType $p): string => $p->code,
            $e->allowedPackageTypes(),
        );
        $model->weight_min_kg = $e->weightLimits()->minKg;
        $model->weight_max_kg = $e->weightLimits()->maxKg;
        $model->dim_max_l_cm = $e->dimensionLimits()->maxLengthCm;
        $model->dim_max_b_cm = $e->dimensionLimits()->maxWidthCm;
        $model->dim_max_h_cm = $e->dimensionLimits()->maxHeightCm;
        $model->valid_from = $e->validFrom();
        $model->valid_until = $e->validUntil();
        $model->deprecated_at = $e->deprecatedAt();
        $model->replaced_by_code = $e->replacedByCode()?->value;
        $model->source = $e->source()->value;
        $model->synced_at = $e->syncedAt();

        return $model;
    }

    // ---------------------------------------------------------------------
    // Additional Service
    // ---------------------------------------------------------------------

    public function toServiceEntity(DhlAdditionalServiceModel $m): DhlAdditionalService
    {
        $schemaRaw = is_array($m->parameter_schema) ? $m->parameter_schema : [];

        return new DhlAdditionalService(
            code: (string) $m->code,
            name: (string) $m->name,
            description: (string) $m->description,
            category: DhlServiceCategory::fromString((string) $m->category),
            parameterSchema: JsonSchema::fromArray($schemaRaw),
            deprecatedAt: $this->toNullableImmutable($m->deprecated_at),
            source: DhlCatalogSource::fromString((string) $m->source),
            syncedAt: $this->toNullableImmutable($m->synced_at),
        );
    }

    public function toServiceModel(
        DhlAdditionalService $e,
        ?DhlAdditionalServiceModel $existing = null,
    ): DhlAdditionalServiceModel {
        $model = $existing ?? new DhlAdditionalServiceModel;

        $model->code = $e->code();
        $model->name = $e->name();
        $model->description = $e->description();
        $model->category = $e->category()->value;
        $model->parameter_schema = $e->parameterSchema()->toArray();
        $model->deprecated_at = $e->deprecatedAt();
        $model->source = $e->source()->value;
        $model->synced_at = $e->syncedAt();

        return $model;
    }

    // ---------------------------------------------------------------------
    // Assignment
    // ---------------------------------------------------------------------

    public function toAssignmentEntity(
        DhlProductServiceAssignmentModel $m,
    ): DhlProductServiceAssignment {
        return new DhlProductServiceAssignment(
            productCode: new DhlProductCode((string) $m->product_code),
            serviceCode: (string) $m->service_code,
            fromCountry: $m->from_country !== null
                ? new CountryCode((string) $m->from_country)
                : null,
            toCountry: $m->to_country !== null
                ? new CountryCode((string) $m->to_country)
                : null,
            payerCode: $m->payer_code !== null
                ? DhlPayerCode::fromString((string) $m->payer_code)
                : null,
            requirement: DhlServiceRequirement::fromString((string) $m->requirement),
            defaultParameters: is_array($m->default_parameters) ? $m->default_parameters : [],
            source: DhlCatalogSource::fromString((string) $m->source),
            syncedAt: $this->toNullableImmutable($m->synced_at),
        );
    }

    public function toAssignmentModel(
        DhlProductServiceAssignment $e,
        ?DhlProductServiceAssignmentModel $existing = null,
    ): DhlProductServiceAssignmentModel {
        $model = $existing ?? new DhlProductServiceAssignmentModel;

        $model->product_code = $e->productCode()->value;
        $model->service_code = $e->serviceCode();
        $model->from_country = $e->fromCountry()?->value;
        $model->to_country = $e->toCountry()?->value;
        $model->payer_code = $e->payerCode()?->value;
        $model->requirement = $e->requirement()->value;
        $model->default_parameters = $e->defaultParameters() === [] ? null : $e->defaultParameters();
        $model->source = $e->source()->value;
        $model->synced_at = $e->syncedAt();

        return $model;
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * @param  mixed  $raw
     * @return list<CountryCode>
     */
    private function toCountryList(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $value) {
            if (is_string($value) && $value !== '') {
                $out[] = new CountryCode($value);
            }
        }

        return $out;
    }

    /**
     * @param  mixed  $raw
     * @return list<DhlPackageType>
     */
    private function toPackageTypeList(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $value) {
            if (is_string($value) && $value !== '') {
                $out[] = new DhlPackageType($value);
            }
        }

        return $out;
    }

    private function toImmutable(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }
        if (is_string($value) && $value !== '') {
            return new DateTimeImmutable($value);
        }
        throw new \InvalidArgumentException('Cannot map value to DateTimeImmutable.');
    }

    private function toNullableImmutable(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        return $this->toImmutable($value);
    }
}
