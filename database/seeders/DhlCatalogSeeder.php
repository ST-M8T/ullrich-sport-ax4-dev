<?php

declare(strict_types=1);

namespace Database\Seeders;

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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Idempotent seeder for the DHL catalog (PROJ-1).
 *
 * Reads JSON fixtures from `database/seeders/data/dhl/`:
 *   - `products.json`     -> list of product payloads
 *   - `services.json`     -> list of additional service payloads
 *   - `assignments.json`  -> list of product↔service assignment payloads
 *
 * Idempotency is provided by the repositories (upsert semantics via primary
 * key). Missing files are silently skipped — this seeder is a no-op until
 * fixtures land in the data directory.
 *
 * Engineering-Handbuch §24 (Idempotenz), §13 (Mapper-Regel — explicit).
 */
final class DhlCatalogSeeder extends Seeder
{
    private const DATA_DIR = 'data/dhl';

    private const FILE_PRODUCTS = 'products.json';

    private const FILE_SERVICES = 'services.json';

    private const FILE_ASSIGNMENTS = 'assignments.json';

    private const FILE_MANIFEST = '_manifest.json';

    private const ACTOR_SUBSYSTEM = 'dhl-seed';

    private const MANIFEST_MAX_AGE_DAYS = 90;

    public function __construct(
        private readonly DhlProductRepository $products,
        private readonly DhlAdditionalServiceRepository $services,
        private readonly DhlProductServiceAssignmentRepository $assignments,
    ) {}

    public function run(): void
    {
        $actor = AuditActor::system(self::ACTOR_SUBSYSTEM);
        $logger = Log::channel('dhl-catalog');

        if (! $this->manifestIsFresh($logger)) {
            return;
        }

        foreach ($this->loadFixture(self::FILE_PRODUCTS) as $row) {
            try {
                $product = $this->hydrateProduct($row);
            } catch (Throwable $e) {
                $logger->warning('dhl.catalog.seed.product_invalid', [
                    'code' => $row['code'] ?? null,
                    'message' => $e->getMessage(),
                ]);

                continue;
            }
            if (! $this->shouldSeedProduct($product->code())) {
                continue;
            }
            try {
                $this->products->save($product, $actor);
            } catch (Throwable $e) {
                $logger->warning('dhl.catalog.seed.product_save_failed', [
                    'code' => $product->code()->value,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        foreach ($this->loadFixture(self::FILE_SERVICES) as $row) {
            try {
                $service = $this->hydrateService($row);
            } catch (Throwable $e) {
                $logger->warning('dhl.catalog.seed.service_invalid', [
                    'code' => $row['code'] ?? null,
                    'message' => $e->getMessage(),
                ]);

                continue;
            }
            if (! $this->shouldSeedService($service->code())) {
                continue;
            }
            try {
                $this->services->save($service, $actor);
            } catch (Throwable $e) {
                $logger->warning('dhl.catalog.seed.service_save_failed', [
                    'code' => $service->code(),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        foreach ($this->loadFixture(self::FILE_ASSIGNMENTS) as $row) {
            try {
                $assignment = $this->hydrateAssignment($row);
                $this->assignments->save($assignment, $actor);
            } catch (Throwable $e) {
                $logger->warning('dhl.catalog.seed.assignment_failed', [
                    'product' => $row['product_code'] ?? null,
                    'service' => $row['service_code'] ?? null,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    private function manifestIsFresh(\Psr\Log\LoggerInterface $logger): bool
    {
        $path = database_path(self::DATA_DIR . '/' . self::FILE_MANIFEST);
        if (! is_file($path)) {
            return true; // Manifest optional — fixtures may pre-date manifest mechanism.
        }
        $raw = (string) file_get_contents($path);
        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || ! isset($decoded['fetched_at'])) {
            return true;
        }
        $fetchedAt = strtotime((string) $decoded['fetched_at']);
        if ($fetchedAt === false) {
            return true;
        }
        $ageDays = (int) floor((time() - $fetchedAt) / 86400);
        if ($ageDays > self::MANIFEST_MAX_AGE_DAYS) {
            $logger->warning('dhl.catalog.seed.manifest_stale', [
                'age_days' => $ageDays,
                'max_age_days' => self::MANIFEST_MAX_AGE_DAYS,
            ]);
        }

        return true; // Always seed; stale manifests get a warning but don't block.
    }

    /**
     * Idempotenz-Schutz: ein bestehender Eintrag mit `source='api'` oder
     * `source='manual'` darf vom Seeder NICHT überschrieben werden.
     */
    private function shouldSeedProduct(DhlProductCode $code): bool
    {
        $existing = $this->products->findByCode($code);
        if ($existing === null) {
            return true;
        }

        return $existing->source() === DhlCatalogSource::SEED;
    }

    private function shouldSeedService(string $code): bool
    {
        $existing = $this->services->findByCode($code);
        if ($existing === null) {
            return true;
        }

        return $existing->source() === DhlCatalogSource::SEED;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function loadFixture(string $filename): array
    {
        $path = database_path(self::DATA_DIR . '/' . $filename);
        if (! is_file($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        /** @var list<array<string,mixed>> $rows */
        $rows = array_values(array_filter($decoded, 'is_array'));

        return $rows;
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function hydrateProduct(array $row): DhlProduct
    {
        return new DhlProduct(
            code: new DhlProductCode((string) $row['code']),
            name: (string) $row['name'],
            description: (string) ($row['description'] ?? ''),
            marketAvailability: DhlMarketAvailability::fromString((string) $row['market_availability']),
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
            validUntil: isset($row['valid_until']) && $row['valid_until'] !== null
                ? new DateTimeImmutable((string) $row['valid_until'])
                : null,
            deprecatedAt: isset($row['deprecated_at']) && $row['deprecated_at'] !== null
                ? new DateTimeImmutable((string) $row['deprecated_at'])
                : null,
            replacedByCode: isset($row['replaced_by_code']) && $row['replaced_by_code'] !== null
                ? new DhlProductCode((string) $row['replaced_by_code'])
                : null,
            source: DhlCatalogSource::fromString((string) ($row['source'] ?? 'seed')),
            syncedAt: isset($row['synced_at']) && $row['synced_at'] !== null
                ? new DateTimeImmutable((string) $row['synced_at'])
                : null,
        );
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function hydrateService(array $row): DhlAdditionalService
    {
        /** @var array<string,mixed> $schema */
        $schema = is_array($row['parameter_schema'] ?? null)
            ? $row['parameter_schema']
            : ['type' => 'object'];

        return new DhlAdditionalService(
            code: (string) $row['code'],
            name: (string) $row['name'],
            description: (string) ($row['description'] ?? ''),
            category: DhlServiceCategory::fromString((string) $row['category']),
            parameterSchema: JsonSchema::fromArray($schema),
            deprecatedAt: isset($row['deprecated_at']) && $row['deprecated_at'] !== null
                ? new DateTimeImmutable((string) $row['deprecated_at'])
                : null,
            source: DhlCatalogSource::fromString((string) ($row['source'] ?? 'seed')),
            syncedAt: isset($row['synced_at']) && $row['synced_at'] !== null
                ? new DateTimeImmutable((string) $row['synced_at'])
                : null,
        );
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function hydrateAssignment(array $row): DhlProductServiceAssignment
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
            syncedAt: isset($row['synced_at']) && $row['synced_at'] !== null
                ? new DateTimeImmutable((string) $row['synced_at'])
                : null,
        );
    }

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
}
