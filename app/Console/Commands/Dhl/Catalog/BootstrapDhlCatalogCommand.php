<?php

declare(strict_types=1);

namespace App\Console\Commands\Dhl\Catalog;

use App\Application\Fulfillment\Integrations\Dhl\Catalog\DhlCatalogBootstrapper;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * CLI-Adapter (Presentation, §7) für die Bootstrap-Phase des
 * DHL-Katalog-Sync-Lifecycles. Hält keine Fachlogik — delegiert an
 * {@see DhlCatalogBootstrapper} und schreibt die Ergebnisse als
 * versionierte JSON-Fixtures.
 */
final class BootstrapDhlCatalogCommand extends Command
{
    protected $signature = 'dhl:catalog:bootstrap
        {--routing= : Routing filter as "FROM-TO" (e.g. DE-AT). If omitted, all configured routings are used.}
        {--dry-run : Do not write fixture files, only print statistics.}
        {--force : Overwrite existing fixtures even when they appear current.}';

    protected $description = 'Pulls the DHL catalog (products, services, assignments) from the live API and writes JSON fixtures.';

    private const FIXTURE_DIR = 'data/dhl';

    private const FILE_PRODUCTS = 'products.json';

    private const FILE_SERVICES = 'services.json';

    private const FILE_ASSIGNMENTS = 'assignments.json';

    private const FILE_MANIFEST = '_manifest.json';

    private const GENERATOR_VERSION = 'PROJ-2';

    public function handle(DhlCatalogBootstrapper $bootstrapper): int
    {
        $logger = Log::channel('dhl-catalog');

        try {
            [$fromCountries, $toCountries] = $this->resolveCountries();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $payerCodes = $this->resolvePayerCodes();

        if ($fromCountries === [] || $toCountries === [] || $payerCodes === []) {
            $this->error('No routings or payer codes configured. Check config/dhl-catalog.php.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Bootstrapping DHL catalog: from=[%s] to=[%s] payer=[%s]%s%s',
            implode(',', $fromCountries),
            implode(',', $toCountries),
            implode(',', $payerCodes),
            $this->option('dry-run') ? ' [DRY-RUN]' : '',
            $this->option('force') ? ' [FORCE]' : '',
        ));

        $startedAt = microtime(true);

        try {
            $result = $bootstrapper->bootstrap($fromCountries, $toCountries, $payerCodes);
        } catch (RuntimeException $e) {
            if ($e->getCode() === 401) {
                $this->error($e->getMessage());
                $logger->error('dhl.catalog.bootstrap.auth_failed', [
                    'message' => $e->getMessage(),
                ]);

                return self::FAILURE;
            }
            throw $e;
        } catch (Throwable $e) {
            $this->error('Bootstrap aborted: ' . $e->getMessage());
            $logger->error('dhl.catalog.bootstrap.exception', [
                'message' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

        $this->renderSummary($result, $durationMs);

        $logger->info('dhl.catalog.bootstrap.completed', [
            'counts' => $result['counts'],
            'duration_ms' => $durationMs,
            'dry_run' => (bool) $this->option('dry-run'),
            'errors_count' => count($result['errors']),
        ]);

        if ($this->option('dry-run')) {
            $this->warn('--dry-run: no files written.');

            return self::SUCCESS;
        }

        $this->writeFixtures($result, $fromCountries, $toCountries, $payerCodes, $durationMs);

        $this->info('Fixtures written to database/' . self::FIXTURE_DIR . '/.');

        return $result['errors'] === [] ? self::SUCCESS : self::SUCCESS; // partial success is still success
    }

    /**
     * @return array{0:list<string>,1:list<string>}
     */
    private function resolveCountries(): array
    {
        /** @var list<string> $configured */
        $configured = (array) config('dhl-catalog.default_countries', []);
        $configured = array_values(array_filter(array_map(
            static fn ($c): string => is_string($c) ? strtoupper(trim($c)) : '',
            $configured,
        )));

        $routingOption = $this->option('routing');
        if (is_string($routingOption) && $routingOption !== '') {
            $parts = explode('-', strtoupper(trim($routingOption)));
            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                throw new RuntimeException('Invalid --routing format. Use "FROM-TO", e.g. "DE-AT".');
            }

            return [[$parts[0]], [$parts[1]]];
        }

        return [$configured, $configured];
    }

    /**
     * @return list<string>
     */
    private function resolvePayerCodes(): array
    {
        /** @var list<string> $codes */
        $codes = (array) config('dhl-catalog.default_payer_codes', []);

        return array_values(array_filter(array_map(
            static fn ($c): string => is_string($c) ? strtoupper(trim($c)) : '',
            $codes,
        )));
    }

    /**
     * @param  array{products:list<array<string,mixed>>,services:list<array<string,mixed>>,assignments:list<array<string,mixed>>,errors:list<array<string,mixed>>,counts:array<string,int>}  $result
     */
    private function renderSummary(array $result, int $durationMs): void
    {
        $this->table(
            ['Entity', 'Count'],
            [
                ['Products', (string) $result['counts']['products']],
                ['Services', (string) $result['counts']['services']],
                ['Assignments', (string) $result['counts']['assignments']],
                ['Errors', (string) $result['counts']['errors']],
                ['Duration (ms)', (string) $durationMs],
            ],
        );

        if ($result['errors'] !== []) {
            $this->warn(sprintf('%d non-fatal errors occurred (see log channel "dhl-catalog").', count($result['errors'])));
        }
    }

    /**
     * @param  array{products:list<array<string,mixed>>,services:list<array<string,mixed>>,assignments:list<array<string,mixed>>,errors:list<array<string,mixed>>,counts:array<string,int>}  $result
     * @param  list<string>  $fromCountries
     * @param  list<string>  $toCountries
     * @param  list<string>  $payerCodes
     */
    private function writeFixtures(
        array $result,
        array $fromCountries,
        array $toCountries,
        array $payerCodes,
        int $durationMs,
    ): void {
        $dir = database_path(self::FIXTURE_DIR);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0o755, true);
        }

        $manifestPath = $dir . '/' . self::FILE_MANIFEST;
        if (! $this->option('force') && File::exists($manifestPath)) {
            $existing = json_decode((string) File::get($manifestPath), true);
            if (is_array($existing) && isset($existing['fetched_at'])) {
                $fetchedAt = strtotime((string) $existing['fetched_at']);
                if ($fetchedAt !== false && (time() - $fetchedAt) < 86400) {
                    $this->warn('Existing manifest is less than 24h old. Use --force to overwrite.');

                    return;
                }
            }
        }

        $this->writeJsonAtomic($dir . '/' . self::FILE_PRODUCTS, $result['products']);
        $this->writeJsonAtomic($dir . '/' . self::FILE_SERVICES, $result['services']);
        $this->writeJsonAtomic($dir . '/' . self::FILE_ASSIGNMENTS, $result['assignments']);

        $manifest = [
            'dhl_api_version' => 'v2',
            'dhl_api_base_url_host' => parse_url((string) config('services.dhl.freight.base_url', ''), PHP_URL_HOST) ?: null,
            'fetched_at' => (new DateTimeImmutable)->format(DATE_ATOM),
            'from_countries' => $fromCountries,
            'to_countries' => $toCountries,
            'payer_codes' => $payerCodes,
            'counts' => $result['counts'],
            'duration_ms' => $durationMs,
            'sandbox' => $this->isSandbox(),
            'generated_by' => 'dhl:catalog:bootstrap',
            'generator_version' => self::GENERATOR_VERSION,
        ];
        $this->writeJsonAtomic($manifestPath, $manifest);
    }

    private function writeJsonAtomic(string $path, mixed $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Failed to encode JSON fixture: ' . $path);
        }
        $tmp = $path . '.tmp';
        File::put($tmp, $json . "\n");
        File::move($tmp, $path);
    }

    private function isSandbox(): bool
    {
        $host = (string) parse_url((string) config('services.dhl.freight.base_url', ''), PHP_URL_HOST);

        return $host !== '' && str_contains(strtolower($host), 'sandbox');
    }
}
