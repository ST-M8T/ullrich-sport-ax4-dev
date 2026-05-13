<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Seeders;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlAdditionalServiceRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlProductRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlCatalogSource;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlProductModel;
use Database\Seeders\DhlCatalogSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class DhlCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureDir;

    /** @var array<string,mixed> */
    private array $originalFixtureSnapshot = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureDir = database_path('data/dhl');
        if (! File::isDirectory($this->fixtureDir)) {
            File::makeDirectory($this->fixtureDir, 0o755, true);
        }
        foreach (['products.json', 'services.json', 'assignments.json', '_manifest.json'] as $f) {
            $path = $this->fixtureDir . '/' . $f;
            if (File::exists($path)) {
                $this->originalFixtureSnapshot[$f] = File::get($path);
            }
        }
    }

    protected function tearDown(): void
    {
        foreach (['products.json', 'services.json', 'assignments.json', '_manifest.json'] as $f) {
            $path = $this->fixtureDir . '/' . $f;
            if (isset($this->originalFixtureSnapshot[$f])) {
                File::put($path, $this->originalFixtureSnapshot[$f]);
            } elseif (File::exists($path)) {
                File::delete($path);
            }
        }
        parent::tearDown();
    }

    public function test_seeder_persists_fixtures_idempotently(): void
    {
        $this->writeFixture('products.json', [
            $this->productFixture('ECI'),
        ]);
        $this->writeFixture('services.json', [
            $this->serviceFixture('NOT'),
        ]);
        $this->writeFixture('assignments.json', [
            $this->assignmentFixture('ECI', 'NOT'),
        ]);
        $this->writeFixture('_manifest.json', [
            'fetched_at' => (new DateTimeImmutable)->format(DATE_ATOM),
            'counts' => ['products' => 1, 'services' => 1, 'assignments' => 1],
        ]);

        $this->seed(DhlCatalogSeeder::class);

        $products = $this->app->make(DhlProductRepository::class);
        $services = $this->app->make(DhlAdditionalServiceRepository::class);

        $this->assertNotNull($products->findByCode(new DhlProductCode('ECI')));
        $this->assertNotNull($services->findByCode('NOT'));

        // Second run = no exceptions, identical state.
        $this->seed(DhlCatalogSeeder::class);
        $this->assertNotNull($products->findByCode(new DhlProductCode('ECI')));
    }

    public function test_seeder_does_not_overwrite_manual_entries(): void
    {
        $this->writeFixture('products.json', [
            $this->productFixture('ECI', name: 'Seeded Name'),
        ]);
        $this->writeFixture('services.json', []);
        $this->writeFixture('assignments.json', []);

        // Pre-create a manual entry directly via Eloquent
        DhlProductModel::query()->create([
            'code' => 'ECI',
            'name' => 'Manual Override',
            'description' => '',
            'market_availability' => 'B2B',
            'from_countries' => ['DE'],
            'to_countries' => ['AT'],
            'allowed_package_types' => ['PLT'],
            'weight_min_kg' => 0.0,
            'weight_max_kg' => 2500.0,
            'dim_max_l_cm' => 240.0,
            'dim_max_b_cm' => 120.0,
            'dim_max_h_cm' => 220.0,
            'valid_from' => '2020-01-01 00:00:00',
            'valid_until' => null,
            'deprecated_at' => null,
            'replaced_by_code' => null,
            'source' => DhlCatalogSource::MANUAL->value,
            'synced_at' => null,
        ]);

        $this->seed(DhlCatalogSeeder::class);

        $row = DhlProductModel::query()->whereKey('ECI')->firstOrFail();
        $this->assertSame('Manual Override', $row->name);
        $this->assertSame(DhlCatalogSource::MANUAL->value, $row->source);
    }

    /**
     * @param  list<array<string,mixed>>|array<string,mixed>  $data
     */
    private function writeFixture(string $filename, array $data): void
    {
        File::put(
            $this->fixtureDir . '/' . $filename,
            (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function productFixture(string $code, string $name = 'Test Product'): array
    {
        return [
            'code' => $code,
            'name' => $name,
            'description' => 'Fixture',
            'market_availability' => 'B2B',
            'from_countries' => ['DE'],
            'to_countries' => ['AT'],
            'allowed_package_types' => ['PLT'],
            'weight_min_kg' => 0.0,
            'weight_max_kg' => 2500.0,
            'dim_max_l_cm' => 240.0,
            'dim_max_b_cm' => 120.0,
            'dim_max_h_cm' => 220.0,
            'valid_from' => '2020-01-01T00:00:00Z',
            'source' => 'seed',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serviceFixture(string $code): array
    {
        return [
            'code' => $code,
            'name' => 'Test Service',
            'description' => 'Fixture',
            'category' => 'notification',
            'parameter_schema' => ['type' => 'object'],
            'source' => 'seed',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function assignmentFixture(string $product, string $service): array
    {
        return [
            'product_code' => $product,
            'service_code' => $service,
            'from_country' => 'DE',
            'to_country' => 'AT',
            'payer_code' => 'DAP',
            'requirement' => 'allowed',
            'default_parameters' => [],
            'source' => 'seed',
        ];
    }
}
