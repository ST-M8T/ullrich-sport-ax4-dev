<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Domain\Integrations\Contracts\DhlFreightGateway;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class DhlCatalogBootstrapCommandTest extends TestCase
{
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

        config()->set('dhl-catalog.default_countries', ['DE', 'AT']);
        config()->set('dhl-catalog.default_payer_codes', ['DAP']);
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

    public function test_bootstrap_writes_all_fixture_files(): void
    {
        $this->bindStubGateway();

        $this->artisan('dhl:catalog:bootstrap', ['--force' => true])
            ->assertExitCode(0);

        $this->assertTrue(File::exists($this->fixtureDir . '/products.json'));
        $this->assertTrue(File::exists($this->fixtureDir . '/services.json'));
        $this->assertTrue(File::exists($this->fixtureDir . '/assignments.json'));
        $this->assertTrue(File::exists($this->fixtureDir . '/_manifest.json'));

        $products = json_decode((string) File::get($this->fixtureDir . '/products.json'), true);
        $services = json_decode((string) File::get($this->fixtureDir . '/services.json'), true);
        $assignments = json_decode((string) File::get($this->fixtureDir . '/assignments.json'), true);
        $manifest = json_decode((string) File::get($this->fixtureDir . '/_manifest.json'), true);

        $this->assertIsArray($products);
        $this->assertIsArray($services);
        $this->assertIsArray($assignments);
        $this->assertSame(1, count($products));
        $this->assertSame('ECI', $products[0]['code']);
        $this->assertSame(1, count($services));
        $this->assertSame('NOT', $services[0]['code']);
        $this->assertGreaterThanOrEqual(1, count($assignments));
        $this->assertSame('DAP', $assignments[0]['payer_code']);

        $this->assertIsArray($manifest);
        $this->assertSame($products[0] ? count($products) : 0, $manifest['counts']['products']);
        $this->assertSame('dhl:catalog:bootstrap', $manifest['generated_by']);
        // §30: no auth fields in manifest
        $manifestJson = json_encode($manifest);
        $this->assertStringNotContainsStringIgnoringCase('token', (string) $manifestJson);
        $this->assertStringNotContainsStringIgnoringCase('bearer', (string) $manifestJson);
    }

    public function test_dry_run_does_not_write_files(): void
    {
        // Remove any existing fixtures first
        foreach (['products.json', 'services.json', 'assignments.json', '_manifest.json'] as $f) {
            $path = $this->fixtureDir . '/' . $f;
            if (File::exists($path)) {
                File::delete($path);
            }
        }

        $this->bindStubGateway();

        $this->artisan('dhl:catalog:bootstrap', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertFalse(File::exists($this->fixtureDir . '/products.json'));
        $this->assertFalse(File::exists($this->fixtureDir . '/services.json'));
        $this->assertFalse(File::exists($this->fixtureDir . '/assignments.json'));
        $this->assertFalse(File::exists($this->fixtureDir . '/_manifest.json'));
    }

    public function test_routing_filter_restricts_calls(): void
    {
        $gateway = $this->bindStubGateway();

        $this->artisan('dhl:catalog:bootstrap', ['--routing' => 'DE-AT', '--force' => true])
            ->assertExitCode(0);

        $manifest = json_decode((string) File::get($this->fixtureDir . '/_manifest.json'), true);
        $this->assertSame(['DE'], $manifest['from_countries']);
        $this->assertSame(['AT'], $manifest['to_countries']);

        $listProductsCalls = array_values(array_filter(
            $gateway->calls,
            static fn (array $c): bool => $c['op'] === 'listProducts'
        ));
        foreach ($listProductsCalls as $call) {
            $this->assertSame('DE', $call['filters']['fromCountryCode']);
            $this->assertSame('AT', $call['filters']['toCountryCode']);
        }
    }

    public function test_auth_failure_aborts_with_clear_error(): void
    {
        $this->app->instance(DhlFreightGateway::class, new class implements DhlFreightGateway
        {
            public function listProducts(array $filters = []): array
            {
                $response = new Response(new \GuzzleHttp\Psr7\Response(401, [], '{"error":"unauthorized"}'));

                throw new RequestException($response);
            }

            public function listAdditionalServices(string $productId, array $filters = []): array
            {
                return [];
            }

            public function getTimetable(array $payload): array { return []; }
            public function validateAdditionalServices(string $productId, array $services, array $filters = []): array { return []; }
            public function bookShipment(array $payload): array { return []; }
            public function getPriceQuote(array $quoteModel): array { return []; }
            public function printLabel(string $shipmentId, array $options = []): array { return []; }
            public function printDocuments(array $shipment, array $options = []): array { return []; }
            public function printMultipleDocuments(array $shipments, array $options = []): array { return []; }
            public function ping(): array { return ['status' => 200, 'duration_ms' => 0.0, 'body' => null]; }
            public function cancelShipment(string $shipmentId, string $reason): array { return ['success' => false, 'cancelled_at' => '', 'confirmation_number' => null, 'error' => null]; }
        });

        $this->artisan('dhl:catalog:bootstrap', ['--force' => true])
            ->expectsOutputToContain('authentication failed')
            ->assertExitCode(1);
    }

    private function bindStubGateway(): StubDhlFreightGateway
    {
        $gateway = new StubDhlFreightGateway;
        $this->app->instance(DhlFreightGateway::class, $gateway);

        return $gateway;
    }
}

/**
 * Minimal in-memory gateway used as test double. Returns one product (ECI)
 * with one additional service (NOT) for every routing/payer combination.
 */
final class StubDhlFreightGateway implements DhlFreightGateway
{
    /** @var list<array<string,mixed>> */
    public array $calls = [];

    public function listProducts(array $filters = []): array
    {
        $this->calls[] = ['op' => 'listProducts', 'filters' => $filters];

        return [
            'products' => [
                [
                    'code' => 'ECI',
                    'name' => 'Euroconnect International',
                    'description' => 'Stub product',
                    'marketAvailability' => 'B2B',
                    'allowedPackageTypes' => ['PLT'],
                    'weightMaxKg' => 2500.0,
                ],
            ],
        ];
    }

    public function listAdditionalServices(string $productId, array $filters = []): array
    {
        $this->calls[] = ['op' => 'listAdditionalServices', 'product' => $productId, 'filters' => $filters];

        return [
            'additionalServices' => [
                [
                    'code' => 'NOT',
                    'name' => 'Notification',
                    'category' => 'notification',
                    'requirement' => 'allowed',
                    'parameterSchema' => ['type' => 'object'],
                ],
            ],
        ];
    }

    public function getTimetable(array $payload): array { return []; }
    public function validateAdditionalServices(string $productId, array $services, array $filters = []): array { return ['valid' => true, 'errors' => []]; }
    public function bookShipment(array $payload): array { return []; }
    public function getPriceQuote(array $quoteModel): array { return []; }
    public function printLabel(string $shipmentId, array $options = []): array { return []; }
    public function printDocuments(array $shipment, array $options = []): array { return []; }
    public function printMultipleDocuments(array $shipments, array $options = []): array { return []; }
    public function ping(): array { return ['status' => 200, 'duration_ms' => 0.0, 'body' => null]; }
    public function cancelShipment(string $shipmentId, string $reason): array { return ['success' => true, 'cancelled_at' => '', 'confirmation_number' => null, 'error' => null]; }
}
