<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Catalog\Queries;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\CountryCode;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlServiceRequirement;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPayerCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;

/**
 * Bulk-booking helper for PROJ-5: given a list of routing contexts (one per
 * shipment in a batch), compute
 *  - the set of services allowed in ALL routings (intersection on ALLOWED+REQUIRED)
 *  - the union of services REQUIRED in any routing (must be applied at least to
 *    that subset; surfaced for the UI so the user sees them)
 *
 * Pure orchestration on top of {@see GetAllowedDhlServices}. No own caching —
 * called rarely (bulk path) and benefits from per-routing cache hits.
 *
 * Engineering-Handbuch §5, §11 (uses repository ports via the underlying query).
 */
final class ComputeAllowedDhlServicesIntersection
{
    public function __construct(
        private readonly GetAllowedDhlServices $singleQuery,
    ) {}

    /**
     * @param  list<array{product:DhlProductCode,from:CountryCode,to:CountryCode,payer:DhlPayerCode}>  $routings
     * @return array<string,mixed>
     */
    public function execute(array $routings): array
    {
        if ($routings === []) {
            return [
                'context' => ['routings_count' => 0],
                'services' => [],
            ];
        }

        /** @var array<int,array<string,array<string,mixed>>> $perRouting */
        $perRouting = [];
        $codesPerRouting = [];

        foreach ($routings as $idx => $routing) {
            $payload = $this->singleQuery->execute(
                $routing['product'],
                $routing['from'],
                $routing['to'],
                $routing['payer'],
            );

            $indexed = [];
            $codes = [];
            foreach ($payload['services'] as $service) {
                $indexed[$service['code']] = $service;
                $codes[$service['code']] = true;
            }
            $perRouting[$idx] = $indexed;
            $codesPerRouting[] = $codes;
        }

        // Intersection of codes across all routings.
        $commonCodes = array_keys($codesPerRouting[0]);
        for ($i = 1, $n = count($codesPerRouting); $i < $n; $i++) {
            $commonCodes = array_values(array_filter(
                $commonCodes,
                static fn (string $c): bool => isset($codesPerRouting[$i][$c]),
            ));
        }

        // Build merged entries: requirement = REQUIRED if any routing marks it required.
        $services = [];
        foreach ($commonCodes as $code) {
            $merged = $perRouting[0][$code];
            $anyRequired = false;
            foreach ($perRouting as $byCode) {
                if (($byCode[$code]['requirement'] ?? null) === DhlServiceRequirement::REQUIRED->value) {
                    $anyRequired = true;
                    break;
                }
            }
            if ($anyRequired) {
                $merged['requirement'] = DhlServiceRequirement::REQUIRED->value;
            }
            $services[] = $merged;
        }

        usort($services, static function (array $a, array $b): int {
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
                'routings_count' => count($routings),
            ],
            'services' => $services,
        ];
    }
}
