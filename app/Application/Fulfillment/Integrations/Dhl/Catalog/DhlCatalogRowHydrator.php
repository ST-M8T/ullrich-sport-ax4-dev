<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Catalog;

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
use DateTimeImmutable;

/**
 * Stateless hydrator that maps fixture / bootstrap-API rows into the catalog
 * domain entities. Lives in Application because the rows are an external
 * contract (DHL API / JSON fixtures), not a domain artifact.
 *
 * Engineering-Handbuch §13 (Mapper-Regel): the contract is explicit, every
 * field is named — no spread / hydrate magic.
 *
 * Reused by:
 *  - {@see SynchroniseDhlCatalogService} for live API rows
 *  - {@see \Database\Seeders\DhlCatalogSeeder} for static fixtures
 *
 * Both consumers ship rows with the SAME shape; DRY §75.
 */
final class DhlCatalogRowHydrator
{
    /**
     * @param  array<string,mixed>  $row
     */
    public function hydrateProduct(array $row): DhlProduct
    {
        return new DhlProduct(
            code: new DhlProductCode((string) $row['code']),
            name: (string) $row['name'],
            description: (string) ($row['description'] ?? ''),
            marketAvailability: DhlMarketAvailability::fromString((string) ($row['market_availability'] ?? 'BOTH')),
            fromCountries: $this->toCountryList($row['from_countries'] ?? []),
            toCountries: $this->toCountryList($row['to_countries'] ?? []),
            allowedPackageTypes: $this->toPackageTypeList($row['allowed_package_types'] ?? []),
            weightLimits: new WeightLimits(
                minKg: (float) ($row['weight_min_kg'] ?? 0.0),
                maxKg: (float) ($row['weight_max_kg'] ?? 0.0),
            ),
            dimensionLimits: new DimensionLimits(
                maxLengthCm: (float) ($row['dim_max_l_cm'] ?? 0.0),
                maxWidthCm: (float) ($row['dim_max_b_cm'] ?? 0.0),
                maxHeightCm: (float) ($row['dim_max_h_cm'] ?? 0.0),
            ),
            validFrom: new DateTimeImmutable((string) ($row['valid_from'] ?? 'now')),
            validUntil: $this->nullableDate($row['valid_until'] ?? null),
            deprecatedAt: $this->nullableDate($row['deprecated_at'] ?? null),
            replacedByCode: isset($row['replaced_by_code']) && $row['replaced_by_code'] !== null
                ? new DhlProductCode((string) $row['replaced_by_code'])
                : null,
            source: DhlCatalogSource::fromString((string) ($row['source'] ?? 'seed')),
            syncedAt: $this->nullableDate($row['synced_at'] ?? null),
        );
    }

    /**
     * @param  array<string,mixed>  $row
     */
    public function hydrateService(array $row): DhlAdditionalService
    {
        /** @var array<string,mixed> $schema */
        $schema = is_array($row['parameter_schema'] ?? null)
            ? $row['parameter_schema']
            : ['type' => 'object'];

        return new DhlAdditionalService(
            code: (string) $row['code'],
            name: (string) $row['name'],
            description: (string) ($row['description'] ?? ''),
            category: DhlServiceCategory::fromString((string) ($row['category'] ?? 'special')),
            parameterSchema: JsonSchema::fromArray($schema),
            deprecatedAt: $this->nullableDate($row['deprecated_at'] ?? null),
            source: DhlCatalogSource::fromString((string) ($row['source'] ?? 'seed')),
            syncedAt: $this->nullableDate($row['synced_at'] ?? null),
        );
    }

    /**
     * @param  array<string,mixed>  $row
     */
    public function hydrateAssignment(array $row): DhlProductServiceAssignment
    {
        /** @var array<string,mixed> $defaults */
        $defaults = is_array($row['default_parameters'] ?? null)
            ? $row['default_parameters']
            : [];

        return new DhlProductServiceAssignment(
            productCode: new DhlProductCode((string) $row['product_code']),
            serviceCode: (string) $row['service_code'],
            fromCountry: isset($row['from_country']) && $row['from_country'] !== null
                ? new CountryCode((string) $row['from_country'])
                : null,
            toCountry: isset($row['to_country']) && $row['to_country'] !== null
                ? new CountryCode((string) $row['to_country'])
                : null,
            payerCode: isset($row['payer_code']) && $row['payer_code'] !== null
                ? DhlPayerCode::fromString((string) $row['payer_code'])
                : null,
            requirement: DhlServiceRequirement::fromString((string) ($row['requirement'] ?? 'allowed')),
            defaultParameters: $defaults,
            source: DhlCatalogSource::fromString((string) ($row['source'] ?? 'seed')),
            syncedAt: $this->nullableDate($row['synced_at'] ?? null),
        );
    }

    public static function assignmentCompositeKey(DhlProductServiceAssignment $a): string
    {
        return sprintf(
            '%s|%s|%s|%s|%s',
            $a->productCode()->value,
            $a->serviceCode(),
            $a->fromCountry()?->value ?? '*',
            $a->toCountry()?->value ?? '*',
            $a->payerCode()?->value ?? '*',
        );
    }

    private function nullableDate(mixed $raw): ?DateTimeImmutable
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if ($raw instanceof DateTimeImmutable) {
            return $raw;
        }
        if (is_string($raw)) {
            return new DateTimeImmutable($raw);
        }

        return null;
    }

    /**
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
                $out[] = new CountryCode(strtoupper($value));
            }
        }

        return $out;
    }

    /**
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
}
