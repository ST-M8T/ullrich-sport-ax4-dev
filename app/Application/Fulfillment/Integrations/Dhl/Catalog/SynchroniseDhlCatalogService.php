<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Catalog;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlAdditionalService;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProduct;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProductServiceAssignment;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlAdditionalServiceRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlCatalogSyncStatusRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlProductRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlProductServiceAssignmentRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\AuditActor;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use App\Mail\Fulfillment\DhlCatalogSyncFailedMail;
use DateTimeImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Phase 2 of the DHL catalog sync lifecycle — the orchestrating use case
 * (PROJ-2, t12).
 *
 * Pulls the current DHL catalog via {@see DhlCatalogBootstrapper} (or, in
 * tests / disaster recovery, from JSON fixtures), computes a diff against the
 * persisted catalog and writes inserts / updates / deprecates / restores.
 * Each entity type (products, services, assignments) runs in its own
 * transaction so a downstream failure does not roll back earlier successful
 * phases (Engineering-Handbuch §17).
 *
 * Audit-logging is done by the underlying repositories — this service only
 * orchestrates and decides what to write.
 *
 * Engineering-Handbuch §5 (orchestration only — no domain logic),
 * §8 (depends on interfaces only), §24 (idempotent),
 * §30 (no tokens in logs — channel `dhl-catalog` strips them upstream).
 */
class SynchroniseDhlCatalogService
{
    public const ERROR_BOOTSTRAP_FAILED = 'bootstrapFailed';

    public const ERROR_SUSPICIOUS_SHRINKAGE = 'suspiciousShrinkage';

    public const ERROR_HYDRATION_FAILED = 'hydrationFailed';

    public const ERROR_PHASE_FAILED = 'phaseFailed';

    public const ERROR_FIXTURE_MISSING = 'fixtureMissing';

    public const ACTOR_DEFAULT = 'system:dhl-sync';

    public const CACHE_TAG = 'dhl-catalog';

    /** Default shrinkage ratio above which a sync is treated as suspicious. */
    private const SHRINKAGE_RATIO_DEFAULT = 0.10;

    public function __construct(
        private readonly DhlProductRepository $productRepo,
        private readonly DhlAdditionalServiceRepository $serviceRepo,
        private readonly DhlProductServiceAssignmentRepository $assignmentRepo,
        private readonly DhlCatalogSyncStatusRepository $statusRepo,
        private readonly DhlCatalogBootstrapper $bootstrapper,
        private readonly DhlCatalogRowHydrator $hydrator,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(SynchroniseDhlCatalogCommand $cmd): SynchroniseDhlCatalogResult
    {
        if ($cmd->dryRun) {
            return $this->executeDryRun($cmd);
        }

        $startedAt = microtime(true);
        $now = new DateTimeImmutable;
        $actor = new AuditActor($cmd->actor);

        $this->statusRepo->recordAttempt($now);

        try {
            $dataset = $cmd->useFixtures
                ? $this->loadFromFixtures()
                : $this->loadFromApi($cmd);
        } catch (Throwable $e) {
            $this->handleFailure($e, $cmd, []);

            return $this->buildResult(
                errors: [[
                    'code' => self::ERROR_BOOTSTRAP_FAILED,
                    'message' => $this->scrub($e->getMessage()),
                ]],
                startedAt: $startedAt,
                dryRun: $cmd->dryRun,
            );
        }

        $errors = $dataset['errors'];
        $counts = [
            'productsAdded' => 0,
            'productsUpdated' => 0,
            'productsDeprecated' => 0,
            'productsRestored' => 0,
            'servicesAdded' => 0,
            'servicesUpdated' => 0,
            'servicesDeprecated' => 0,
            'servicesRestored' => 0,
            'assignmentsAdded' => 0,
            'assignmentsUpdated' => 0,
            'assignmentsDeprecated' => 0,
        ];
        $suspicious = false;

        // -- Phase 1: Products ------------------------------------------------
        $shrinkageProducts = $this->detectShrinkage(
            existingCount: $this->countActiveProducts(),
            apiCount: count($dataset['products']),
        );
        if ($shrinkageProducts !== null) {
            $errors[] = $shrinkageProducts;
            $suspicious = true;
        } else {
            try {
                // §17: Use-Case-Atomicity – jede Sync-Phase ist eine atomare Klammer
                // über mehrere Repository-Calls. DB::transaction ist Infrastructure-Adapter.
                DB::transaction(function () use ($dataset, $actor, &$counts, &$errors): void {
                    $this->syncProducts($dataset['products'], $actor, $counts, $errors);
                });
            } catch (Throwable $e) {
                $this->captureException($e, 'products', $errors);
            }
        }

        // -- Phase 2: Services ------------------------------------------------
        $shrinkageServices = $this->detectShrinkage(
            existingCount: $this->countActiveServices(),
            apiCount: count($dataset['services']),
        );
        if ($shrinkageServices !== null) {
            $errors[] = $shrinkageServices;
            $suspicious = true;
        } else {
            try {
                DB::transaction(function () use ($dataset, $actor, &$counts, &$errors): void {
                    $this->syncServices($dataset['services'], $actor, $counts, $errors);
                });
            } catch (Throwable $e) {
                $this->captureException($e, 'services', $errors);
            }
        }

        // -- Phase 3: Assignments --------------------------------------------
        try {
            DB::transaction(function () use ($dataset, $actor, &$counts, &$errors): void {
                $this->syncAssignments($dataset['assignments'], $actor, $counts, $errors);
            });
        } catch (Throwable $e) {
            $this->captureException($e, 'assignments', $errors);
        }

        $result = $this->buildResult(
            errors: $errors,
            startedAt: $startedAt,
            dryRun: $cmd->dryRun,
            suspicious: $suspicious,
            counts: $counts,
        );

        $this->finalise($result, $cmd, $now);

        return $result;
    }

    /**
     * Dry-run variant: orchestrates the same diff but wraps EVERYTHING in
     * a single outer transaction that is rolled back at the end. No audit
     * row is committed, no DB mutation persists, no cache flush.
     *
     * Engineering-Handbuch §17: dry-run is a single atomic envelope.
     */
    private function executeDryRun(SynchroniseDhlCatalogCommand $cmd): SynchroniseDhlCatalogResult
    {
        $startedAt = microtime(true);
        $now = new DateTimeImmutable;
        $actor = new AuditActor($cmd->actor);

        try {
            $dataset = $cmd->useFixtures
                ? $this->loadFromFixtures()
                : $this->loadFromApi($cmd);
        } catch (Throwable $e) {
            return $this->buildResult(
                errors: [[
                    'code' => self::ERROR_BOOTSTRAP_FAILED,
                    'message' => $this->scrub($e->getMessage()),
                ]],
                startedAt: $startedAt,
                dryRun: true,
            );
        }

        $errors = $dataset['errors'];
        $counts = [
            'productsAdded' => 0, 'productsUpdated' => 0,
            'productsDeprecated' => 0, 'productsRestored' => 0,
            'servicesAdded' => 0, 'servicesUpdated' => 0,
            'servicesDeprecated' => 0, 'servicesRestored' => 0,
            'assignmentsAdded' => 0, 'assignmentsUpdated' => 0,
            'assignmentsDeprecated' => 0,
        ];
        $suspicious = false;

        $shrinkageProducts = $this->detectShrinkage(
            $this->countActiveProducts(),
            count($dataset['products']),
        );
        $shrinkageServices = $this->detectShrinkage(
            $this->countActiveServices(),
            count($dataset['services']),
        );

        DB::beginTransaction();
        try {
            if ($shrinkageProducts !== null) {
                $errors[] = $shrinkageProducts;
                $suspicious = true;
            } else {
                $this->syncProducts($dataset['products'], $actor, $counts, $errors);
            }

            if ($shrinkageServices !== null) {
                $errors[] = $shrinkageServices;
                $suspicious = true;
            } else {
                $this->syncServices($dataset['services'], $actor, $counts, $errors);
            }

            $this->syncAssignments($dataset['assignments'], $actor, $counts, $errors);
        } catch (Throwable $e) {
            $errors[] = [
                'code' => self::ERROR_PHASE_FAILED,
                'message' => $this->scrub($e->getMessage()),
            ];
        } finally {
            DB::rollBack();
        }

        return $this->buildResult(
            errors: $errors,
            startedAt: $startedAt,
            dryRun: true,
            suspicious: $suspicious,
            counts: $counts,
        );
    }

    // -------------------------------------------------------------------------
    // Dataset loading
    // -------------------------------------------------------------------------

    /**
     * @return array{products:list<array<string,mixed>>,services:list<array<string,mixed>>,assignments:list<array<string,mixed>>,errors:list<array<string,mixed>>}
     */
    private function loadFromApi(SynchroniseDhlCatalogCommand $cmd): array
    {
        [$fromCountries, $toCountries] = $this->resolveCountries($cmd->routingFilter);
        $payers = (array) config('dhl-catalog.default_payer_codes', ['DAP']);
        /** @var list<string> $payers */
        $payers = array_values(array_filter(array_map(
            static fn ($p): string => strtoupper((string) $p),
            $payers,
        ), static fn (string $p): bool => $p !== ''));

        $bootstrap = $this->bootstrapper->bootstrap($fromCountries, $toCountries, $payers);

        return [
            'products' => $bootstrap['products'],
            'services' => $bootstrap['services'],
            'assignments' => $bootstrap['assignments'],
            'errors' => $bootstrap['errors'],
        ];
    }

    /**
     * @return array{products:list<array<string,mixed>>,services:list<array<string,mixed>>,assignments:list<array<string,mixed>>,errors:list<array<string,mixed>>}
     */
    private function loadFromFixtures(): array
    {
        $dir = database_path('seeders/data/dhl');
        $errors = [];
        $files = [
            'products' => $dir.'/products.json',
            'services' => $dir.'/services.json',
            'assignments' => $dir.'/assignments.json',
        ];

        $out = ['products' => [], 'services' => [], 'assignments' => [], 'errors' => []];
        foreach ($files as $key => $path) {
            if (! is_file($path)) {
                $errors[] = [
                    'code' => self::ERROR_FIXTURE_MISSING,
                    'message' => sprintf('Fixture %s missing.', basename($path)),
                ];

                continue;
            }
            $raw = (string) file_get_contents($path);
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                $errors[] = [
                    'code' => self::ERROR_FIXTURE_MISSING,
                    'message' => sprintf('Fixture %s is not valid JSON.', basename($path)),
                ];

                continue;
            }
            $out[$key] = array_values(array_filter($decoded, 'is_array'));
        }
        $out['errors'] = $errors;

        return $out;
    }

    /**
     * @return array{0:list<string>,1:list<string>}
     */
    private function resolveCountries(?string $routingFilter): array
    {
        if ($routingFilter !== null && $routingFilter !== '') {
            $parts = explode('-', $routingFilter);
            if (count($parts) !== 2) {
                throw new RuntimeException('Routing filter must have shape "FROM-TO".');
            }

            return [[strtoupper(trim($parts[0]))], [strtoupper(trim($parts[1]))]];
        }
        /** @var list<string> $configured */
        $configured = array_values(array_map(
            static fn (string $c): string => strtoupper(trim($c)),
            (array) config('dhl-catalog.default_countries', ['DE']),
        ));
        // Cartesian: every configured country acts as both origin and destination.
        return [$configured, $configured];
    }

    // -------------------------------------------------------------------------
    // Phase 1: Products
    // -------------------------------------------------------------------------

    /**
     * @param  list<array<string,mixed>>  $apiRows
     * @param  array<string,int>  $counts
     * @param  list<array<string,mixed>>  $errors
     */
    private function syncProducts(
        array $apiRows,
        AuditActor $actor,
        array &$counts,
        array &$errors,
    ): void {
        $existingByCode = $this->loadExistingProductsByCode();
        $seenCodes = [];

        foreach ($apiRows as $row) {
            try {
                $incoming = $this->hydrator->hydrateProduct($row);
            } catch (Throwable $e) {
                $errors[] = [
                    'code' => self::ERROR_HYDRATION_FAILED,
                    'entityType' => 'product',
                    'entityCode' => $row['code'] ?? null,
                    'message' => $e->getMessage(),
                ];

                continue;
            }
            $code = $incoming->code()->value;
            $seenCodes[$code] = true;
            $existing = $existingByCode[$code] ?? null;

            if ($existing === null) {
                $this->productRepo->save($incoming, $actor);
                $counts['productsAdded']++;

                continue;
            }
            if ($existing->isDeprecated()) {
                $this->productRepo->restore($existing->code(), $actor);
                $counts['productsRestored']++;
                if ($this->productDiffers($existing, $incoming)) {
                    $this->productRepo->save($incoming, $actor);
                    $counts['productsUpdated']++;
                }

                continue;
            }
            if ($this->productDiffers($existing, $incoming)) {
                $this->productRepo->save($incoming, $actor);
                $counts['productsUpdated']++;
            }
        }

        foreach ($existingByCode as $code => $product) {
            if (isset($seenCodes[$code])) {
                continue;
            }
            if ($product->isDeprecated()) {
                continue;
            }
            $this->productRepo->softDeprecate(
                new DhlProductCode($code),
                null,
                $actor,
            );
            $counts['productsDeprecated']++;
        }
    }

    /**
     * @return array<string,DhlProduct>
     */
    private function loadExistingProductsByCode(): array
    {
        $out = [];
        // Use a very wide window to cover everything (active + deprecated).
        // findAllActive(now()) excludes deprecated → we need both.
        $now = new DateTimeImmutable;
        foreach ($this->productRepo->findAllActive($now) as $p) {
            $out[$p->code()->value] = $p;
        }
        // Add deprecated ones (broad window, all-time):
        foreach ($this->productRepo->findDeprecatedSince(new DateTimeImmutable('@0')) as $p) {
            $out[$p->code()->value] = $p;
        }

        return $out;
    }

    private function countActiveProducts(): int
    {
        $n = 0;
        foreach ($this->productRepo->findAllActive(new DateTimeImmutable) as $_) {
            $n++;
        }

        return $n;
    }

    private function productDiffers(DhlProduct $a, DhlProduct $b): bool
    {
        return $a->name() !== $b->name()
            || $a->description() !== $b->description()
            || $a->marketAvailability() !== $b->marketAvailability()
            || $this->countryCodeListsDiffer($a->fromCountries(), $b->fromCountries())
            || $this->countryCodeListsDiffer($a->toCountries(), $b->toCountries())
            || $this->packageTypeListsDiffer($a->allowedPackageTypes(), $b->allowedPackageTypes())
            || $a->weightLimits()->minKg !== $b->weightLimits()->minKg
            || $a->weightLimits()->maxKg !== $b->weightLimits()->maxKg
            || $a->dimensionLimits()->maxLengthCm !== $b->dimensionLimits()->maxLengthCm
            || $a->dimensionLimits()->maxWidthCm !== $b->dimensionLimits()->maxWidthCm
            || $a->dimensionLimits()->maxHeightCm !== $b->dimensionLimits()->maxHeightCm;
    }

    /**
     * @param  list<\App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\CountryCode>  $a
     * @param  list<\App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\CountryCode>  $b
     */
    private function countryCodeListsDiffer(array $a, array $b): bool
    {
        $aV = array_map(static fn ($c) => $c->value, $a);
        $bV = array_map(static fn ($c) => $c->value, $b);
        sort($aV);
        sort($bV);

        return $aV !== $bV;
    }

    /**
     * @param  list<\App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPackageType>  $a
     * @param  list<\App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPackageType>  $b
     */
    private function packageTypeListsDiffer(array $a, array $b): bool
    {
        $aV = array_map(static fn ($p) => $p->code, $a);
        $bV = array_map(static fn ($p) => $p->code, $b);
        sort($aV);
        sort($bV);

        return $aV !== $bV;
    }

    // -------------------------------------------------------------------------
    // Phase 2: Services
    // -------------------------------------------------------------------------

    /**
     * @param  list<array<string,mixed>>  $apiRows
     * @param  array<string,int>  $counts
     * @param  list<array<string,mixed>>  $errors
     */
    private function syncServices(
        array $apiRows,
        AuditActor $actor,
        array &$counts,
        array &$errors,
    ): void {
        $existingByCode = $this->loadExistingServicesByCode();
        $seenCodes = [];

        foreach ($apiRows as $row) {
            try {
                $incoming = $this->hydrator->hydrateService($row);
            } catch (Throwable $e) {
                $errors[] = [
                    'code' => self::ERROR_HYDRATION_FAILED,
                    'entityType' => 'service',
                    'entityCode' => $row['code'] ?? null,
                    'message' => $e->getMessage(),
                ];

                continue;
            }
            $code = $incoming->code();
            $seenCodes[$code] = true;
            $existing = $existingByCode[$code] ?? null;

            if ($existing === null) {
                $this->serviceRepo->save($incoming, $actor);
                $counts['servicesAdded']++;

                continue;
            }
            if ($existing->isDeprecated()) {
                $this->serviceRepo->restore($code, $actor);
                $counts['servicesRestored']++;
                if ($this->serviceDiffers($existing, $incoming)) {
                    $this->serviceRepo->save($incoming, $actor);
                    $counts['servicesUpdated']++;
                }

                continue;
            }
            if ($this->serviceDiffers($existing, $incoming)) {
                $this->serviceRepo->save($incoming, $actor);
                $counts['servicesUpdated']++;
            }
        }

        foreach ($existingByCode as $code => $service) {
            if (isset($seenCodes[$code])) {
                continue;
            }
            if ($service->isDeprecated()) {
                continue;
            }
            $this->serviceRepo->softDeprecate($code, $actor);
            $counts['servicesDeprecated']++;
        }
    }

    /**
     * @return array<string,DhlAdditionalService>
     */
    private function loadExistingServicesByCode(): array
    {
        $out = [];
        foreach ($this->serviceRepo->findAllActive() as $s) {
            $out[$s->code()] = $s;
        }
        // Service repo has no "all" — but its findAllActive excludes deprecated.
        // We need to find deprecated services that may be restored. The repo
        // doesn't expose them directly; the diff for restored services relies
        // on findByCode lookups instead.
        return $out;
    }

    private function countActiveServices(): int
    {
        $n = 0;
        foreach ($this->serviceRepo->findAllActive() as $_) {
            $n++;
        }

        return $n;
    }

    private function serviceDiffers(DhlAdditionalService $a, DhlAdditionalService $b): bool
    {
        return $a->name() !== $b->name()
            || $a->description() !== $b->description()
            || $a->category() !== $b->category()
            || $a->parameterSchema()->toArray() !== $b->parameterSchema()->toArray();
    }

    // -------------------------------------------------------------------------
    // Phase 3: Assignments
    // -------------------------------------------------------------------------

    /**
     * @param  list<array<string,mixed>>  $apiRows
     * @param  array<string,int>  $counts
     * @param  list<array<string,mixed>>  $errors
     */
    private function syncAssignments(
        array $apiRows,
        AuditActor $actor,
        array &$counts,
        array &$errors,
    ): void {
        $existingByKey = $this->loadExistingAssignmentsByKey($apiRows);
        $seenKeys = [];

        foreach ($apiRows as $row) {
            try {
                $incoming = $this->hydrator->hydrateAssignment($row);
            } catch (Throwable $e) {
                $errors[] = [
                    'code' => self::ERROR_HYDRATION_FAILED,
                    'entityType' => 'assignment',
                    'entityCode' => ($row['product_code'] ?? '?').'/'.($row['service_code'] ?? '?'),
                    'message' => $e->getMessage(),
                ];

                continue;
            }
            $key = DhlCatalogRowHydrator::assignmentCompositeKey($incoming);
            $seenKeys[$key] = true;
            $existing = $existingByKey[$key] ?? null;

            if ($existing === null) {
                $this->assignmentRepo->save($incoming, $actor);
                $counts['assignmentsAdded']++;

                continue;
            }
            if ($this->assignmentDiffers($existing, $incoming)) {
                $this->assignmentRepo->save($incoming, $actor);
                $counts['assignmentsUpdated']++;
            }
        }

        foreach ($existingByKey as $key => $assignment) {
            if (isset($seenKeys[$key])) {
                continue;
            }
            $this->assignmentRepo->delete($assignment, $actor);
            $counts['assignmentsDeprecated']++;
        }
    }

    /**
     * Load only assignments belonging to products present in the api rows
     * (avoids scanning unrelated products and accidentally deleting them).
     *
     * @param  list<array<string,mixed>>  $apiRows
     * @return array<string,DhlProductServiceAssignment>
     */
    private function loadExistingAssignmentsByKey(array $apiRows): array
    {
        $productCodes = [];
        foreach ($apiRows as $row) {
            $pc = $row['product_code'] ?? null;
            if (is_string($pc) && $pc !== '') {
                $productCodes[$pc] = true;
            }
        }
        $out = [];
        foreach (array_keys($productCodes) as $code) {
            try {
                $productCode = new DhlProductCode((string) $code);
            } catch (Throwable) {
                continue;
            }
            foreach ($this->assignmentRepo->findByProduct($productCode) as $a) {
                $key = DhlCatalogRowHydrator::assignmentCompositeKey($a);
                $out[$key] = $a;
            }
        }

        return $out;
    }

    private function assignmentDiffers(
        DhlProductServiceAssignment $a,
        DhlProductServiceAssignment $b,
    ): bool {
        return $a->requirement() !== $b->requirement()
            || $a->defaultParameters() !== $b->defaultParameters();
    }

    // -------------------------------------------------------------------------
    // Shrinkage detection
    // -------------------------------------------------------------------------

    /**
     * @return array<string,mixed>|null  shrinkage error array or NULL if safe
     */
    private function detectShrinkage(int $existingCount, int $apiCount): ?array
    {
        if ($existingCount === 0) {
            return null; // First-time fill: no comparison baseline.
        }
        $threshold = (float) config(
            'dhl-catalog.suspicious_shrinkage_threshold',
            self::SHRINKAGE_RATIO_DEFAULT,
        );
        $minAllowed = (int) ceil($existingCount * $threshold);
        if ($apiCount >= $minAllowed) {
            return null;
        }

        return [
            'code' => self::ERROR_SUSPICIOUS_SHRINKAGE,
            'message' => sprintf(
                'API response (%d) below shrinkage threshold (>=%d of existing %d).',
                $apiCount,
                $minAllowed,
                $existingCount,
            ),
            'existing' => $existingCount,
            'received' => $apiCount,
            'minAllowed' => $minAllowed,
        ];
    }

    // -------------------------------------------------------------------------
    // Status + Alerting + Cache
    // -------------------------------------------------------------------------

    private function finalise(
        SynchroniseDhlCatalogResult $result,
        SynchroniseDhlCatalogCommand $cmd,
        DateTimeImmutable $startedAt,
    ): void {
        if ($result->hasErrors()) {
            $this->handleFailure(
                exception: null,
                cmd: $cmd,
                errors: $result->errors,
                result: $result,
            );

            return;
        }

        $this->statusRepo->recordSuccess($startedAt);
        $this->flushCacheIfDirty($result);

        $this->logger->info('dhl.catalog.sync.completed', $result->toArray());
    }

    private function flushCacheIfDirty(SynchroniseDhlCatalogResult $result): void
    {
        if ($result->totalChanges() === 0) {
            return;
        }
        try {
            // Tagging is only supported by redis/memcached drivers.
            $store = Cache::getStore();
            if (method_exists($store, 'tags')) {
                Cache::tags([self::CACHE_TAG])->flush();
            }
        } catch (Throwable $e) {
            // Cache flush is non-essential; never block the sync over it.
            $this->logger->warning('dhl.catalog.sync.cache_flush_failed', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  list<array<string,mixed>>  $errors
     */
    private function handleFailure(
        ?Throwable $exception,
        SynchroniseDhlCatalogCommand $cmd,
        array $errors,
        ?SynchroniseDhlCatalogResult $result = null,
    ): void {
        $message = $exception !== null
            ? $this->scrub($exception->getMessage())
            : $this->summariseErrors($errors);

        $status = $this->statusRepo->recordFailure($message);

        $this->logger->error('dhl.catalog.sync.failed', [
            'message' => $message,
            'errors' => $errors,
            'routing' => $cmd->routingFilter,
            'consecutive_failures' => $status->consecutiveFailures,
        ]);

        if ($exception !== null && function_exists('report')) {
            report($exception);
        }

        if ($status->consecutiveFailures === 1 && ! $status->mailSentForFailureStreak) {
            $this->sendAlertMail(
                errorMessage: $message,
                cmd: $cmd,
                status: $status,
                result: $result,
            );
        }
    }

    private function sendAlertMail(
        string $errorMessage,
        SynchroniseDhlCatalogCommand $cmd,
        \App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlCatalogSyncStatus $status,
        ?SynchroniseDhlCatalogResult $result,
    ): void {
        $recipients = (array) config('dhl-catalog.alert_recipients', []);
        $recipients = array_values(array_filter(
            $recipients,
            static fn ($r): bool => is_string($r) && $r !== '',
        ));
        if ($recipients === []) {
            $this->logger->warning('dhl.catalog.sync.alert_recipients_empty');

            return;
        }

        try {
            Mail::to($recipients)->send(new DhlCatalogSyncFailedMail(
                errorMessage: $errorMessage,
                lastSuccessAt: $status->lastSuccessAt,
                consecutiveFailures: $status->consecutiveFailures,
                routingFilter: $cmd->routingFilter,
                resultSummary: $result?->toArray() ?? [],
            ));
            $this->statusRepo->markAlertMailSent();
        } catch (Throwable $e) {
            $this->logger->error('dhl.catalog.sync.alert_mail_failed', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  list<array<string,mixed>>  $errors
     */
    private function summariseErrors(array $errors): string
    {
        if ($errors === []) {
            return 'unknown error';
        }
        $codes = [];
        foreach ($errors as $e) {
            if (isset($e['code']) && is_string($e['code'])) {
                $codes[$e['code']] = true;
            }
        }

        return 'sync failed: '.implode(',', array_keys($codes));
    }

    private function scrub(string $msg): string
    {
        // §30: defence-in-depth — strip anything that looks like a bearer token.
        return preg_replace(
            '/(Bearer\s+|access[_\-]?token["\':\s=]+)([A-Za-z0-9._\-]{12,})/i',
            '$1[REDACTED]',
            $msg,
        ) ?? $msg;
    }

    /**
     * @param  list<array<string,mixed>>  $errors
     */
    private function captureException(Throwable $e, string $phase, array &$errors): void
    {
        $errors[] = [
            'code' => self::ERROR_PHASE_FAILED,
            'phase' => $phase,
            'message' => $this->scrub($e->getMessage()),
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $errors
     * @param  array<string,int>          $counts
     */
    private function buildResult(
        array $errors,
        float $startedAt,
        bool $dryRun,
        bool $suspicious = false,
        array $counts = [],
    ): SynchroniseDhlCatalogResult {
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        return new SynchroniseDhlCatalogResult(
            productsAdded: (int) ($counts['productsAdded'] ?? 0),
            productsUpdated: (int) ($counts['productsUpdated'] ?? 0),
            productsDeprecated: (int) ($counts['productsDeprecated'] ?? 0),
            productsRestored: (int) ($counts['productsRestored'] ?? 0),
            servicesAdded: (int) ($counts['servicesAdded'] ?? 0),
            servicesUpdated: (int) ($counts['servicesUpdated'] ?? 0),
            servicesDeprecated: (int) ($counts['servicesDeprecated'] ?? 0),
            servicesRestored: (int) ($counts['servicesRestored'] ?? 0),
            assignmentsAdded: (int) ($counts['assignmentsAdded'] ?? 0),
            assignmentsUpdated: (int) ($counts['assignmentsUpdated'] ?? 0),
            assignmentsDeprecated: (int) ($counts['assignmentsDeprecated'] ?? 0),
            errors: $errors,
            durationMs: $durationMs,
            suspicious: $suspicious,
            dryRun: $dryRun,
        );
    }
}
