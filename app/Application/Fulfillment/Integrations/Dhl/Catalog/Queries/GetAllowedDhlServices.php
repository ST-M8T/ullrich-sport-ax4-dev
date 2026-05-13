<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Catalog\Queries;

use App\Application\Fulfillment\Integrations\Dhl\Catalog\SynchroniseDhlCatalogService;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlAdditionalService;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProductServiceAssignment;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlAdditionalServiceRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlProductServiceAssignmentRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\CountryCode;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlServiceRequirement;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPayerCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Read-only application query for PROJ-5 — returns the list of additional
 * services that are permitted for a concrete (product, from, to, payer) tuple,
 * combined with the full service definition (name, description, schema, etc.).
 *
 * Engineering-Handbuch §5 (orchestration only), §29 (caching), §45 (no domain
 * logic in caching layer — caches the assembled DTO).
 *
 * Cache strategy:
 *   - Tag: 'dhl-catalog' (shared with PROJ-2 sync invalidation).
 *   - Key: deterministic sha1 of normalized params.
 *   - TTL: 300 seconds.
 *   - Tagged caching is only supported by redis/memcached. For non-tagged
 *     stores we fall back to untagged Cache::remember() with a shorter TTL.
 */
final class GetAllowedDhlServices
{
    public const CACHE_TAG = SynchroniseDhlCatalogService::CACHE_TAG;

    public const CACHE_TTL_SECONDS = 300;

    public const CACHE_TTL_UNTAGGED_SECONDS = 60;

    public function __construct(
        private readonly DhlProductServiceAssignmentRepository $assignmentRepo,
        private readonly DhlAdditionalServiceRepository $serviceRepo,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return array<string,mixed>  Response payload ready for JSON encoding.
     */
    public function execute(
        DhlProductCode $product,
        CountryCode $from,
        CountryCode $to,
        DhlPayerCode $payer,
    ): array {
        $key = $this->cacheKey($product, $from, $to, $payer);

        return $this->rememberTagged(
            $key,
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->compute($product, $from, $to, $payer),
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function compute(
        DhlProductCode $product,
        CountryCode $from,
        CountryCode $to,
        DhlPayerCode $payer,
    ): array {
        $assignments = $this->assignmentRepo->findAllowedServicesFor($product, $from, $to, $payer);

        $services = [];
        foreach ($assignments as $assignment) {
            if ($assignment->requirement() === DhlServiceRequirement::FORBIDDEN) {
                continue;
            }

            $service = $this->serviceRepo->findByCode($assignment->serviceCode());
            if ($service === null) {
                // Catalog inconsistency — assignment references missing service.
                // We skip it (fail-soft for read path); sync will heal next run.
                $this->logger->warning('dhl.catalog.allowed_services.missing_service', [
                    'service_code' => $assignment->serviceCode(),
                    'product_code' => $product->value,
                ]);
                continue;
            }

            $services[] = $this->mapEntry($assignment, $service);
        }

        usort($services, static function (array $a, array $b): int {
            // Required first, then alphabetical inside category, then code.
            $rankA = $a['requirement'] === DhlServiceRequirement::REQUIRED->value ? 0 : 1;
            $rankB = $b['requirement'] === DhlServiceRequirement::REQUIRED->value ? 0 : 1;
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }
            $catCmp = strcmp($a['category'], $b['category']);
            if ($catCmp !== 0) {
                return $catCmp;
            }
            return strcmp($a['code'], $b['code']);
        });

        return [
            'context' => [
                'product_code' => $product->value,
                'from_country' => $from->value,
                'to_country' => $to->value,
                'payer_code' => $payer->value,
            ],
            'services' => $services,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function mapEntry(DhlProductServiceAssignment $assignment, DhlAdditionalService $service): array
    {
        return [
            'code' => $service->code(),
            'name' => $service->name(),
            'description' => $service->description(),
            'category' => $service->category()->value,
            'requirement' => $assignment->requirement()->value,
            'deprecated' => $service->isDeprecated(),
            'parameter_schema' => $service->parameterSchema()->toArray(),
            'default_parameters' => $assignment->defaultParameters(),
        ];
    }

    private function cacheKey(
        DhlProductCode $product,
        CountryCode $from,
        CountryCode $to,
        DhlPayerCode $payer,
    ): string {
        $hash = sha1(implode('|', [
            $product->value,
            $from->value,
            $to->value,
            $payer->value,
        ]));

        return 'dhl-catalog:allowed-services:' . $hash;
    }

    /**
     * @param  callable():array<string,mixed>  $producer
     * @return array<string,mixed>
     */
    private function rememberTagged(string $key, int $ttl, callable $producer): array
    {
        try {
            $store = Cache::getStore();
            if (method_exists($store, 'tags')) {
                /** @var array<string,mixed> $value */
                $value = Cache::tags([self::CACHE_TAG])->remember($key, $ttl, $producer);
                return $value;
            }
        } catch (Throwable $e) {
            $this->logger->warning('dhl.catalog.allowed_services.cache_tag_failed', [
                'message' => $e->getMessage(),
            ]);
        }

        /** @var array<string,mixed> $value */
        $value = Cache::remember($key, self::CACHE_TTL_UNTAGGED_SECONDS, $producer);
        return $value;
    }
}
