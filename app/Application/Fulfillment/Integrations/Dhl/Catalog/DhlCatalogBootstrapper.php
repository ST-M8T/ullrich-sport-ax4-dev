<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Catalog;

use App\Application\Fulfillment\Integrations\Dhl\Services\DhlProductCatalogService;
use Illuminate\Http\Client\RequestException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Bootstrap-Phase des DHL-Katalog-Sync-Lifecycles (PROJ-2).
 *
 * Verantwortung: API-Calls für alle Routing × Payer-Kombinationen ausführen,
 * Ergebnisse dedupen und als seeder-kompatible Fixture-Arrays zurückgeben.
 * Schreibt nicht selbst — das Dateisystem-IO macht der CLI-Adapter.
 *
 * Engineering-Handbuch §5 (Application orchestriert), §22 (klare Fehler),
 * §24 (Idempotenz), §27 (Import-Robustheit).
 */
class DhlCatalogBootstrapper
{
    public const ERROR_AUTH_FAILED = 'authFailed';

    public const ERROR_API_UNAVAILABLE = 'apiUnavailable';

    public const ERROR_SCHEMA_INVALID = 'schemaInvalid';

    public const ERROR_ROUTING_NOT_FOUND = 'routingNotFound';

    public function __construct(
        private readonly DhlProductCatalogService $catalog,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param  list<string>  $fromCountries
     * @param  list<string>  $toCountries
     * @param  list<string>  $payerCodes
     * @return array{products:list<array<string,mixed>>,services:list<array<string,mixed>>,assignments:list<array<string,mixed>>,errors:list<array<string,mixed>>,counts:array<string,int>}
     */
    public function bootstrap(
        array $fromCountries,
        array $toCountries,
        array $payerCodes,
    ): array {
        $products = [];   // code => merged product row
        $services = [];   // code => merged service row
        $assignments = []; // composite-key => assignment row
        $errors = [];

        foreach ($fromCountries as $from) {
            foreach ($toCountries as $to) {
                foreach ($payerCodes as $payer) {
                    try {
                        $productList = $this->catalog->listProductsForRouting($from, $to, $payer);
                    } catch (Throwable $e) {
                        $error = $this->classify($e, $from, $to, $payer);
                        if ($error['code'] === self::ERROR_AUTH_FAILED) {
                            // §22: Auth-Fehler nicht roh weiterreichen,
                            // sofort abbrechen mit klarer Meldung.
                            throw new RuntimeException(
                                'DHL API authentication failed (401). Refresh DHL_API_TOKEN and retry.',
                                401,
                            );
                        }
                        if ($error['code'] === self::ERROR_ROUTING_NOT_FOUND) {
                            // 404 → Routing existiert nicht, ist toleriert.
                            $this->logger->info('dhl.catalog.bootstrap.routing_missing', $error);

                            continue;
                        }
                        $errors[] = $error;
                        $this->logger->warning('dhl.catalog.bootstrap.routing_failed', $error);

                        continue;
                    }

                    foreach ($productList['products'] as $rawProduct) {
                        $productRow = $this->mapProduct($rawProduct, $from, $to);
                        if ($productRow === null) {
                            $errors[] = [
                                'code' => self::ERROR_SCHEMA_INVALID,
                                'from' => $from,
                                'to' => $to,
                                'payer' => $payer,
                                'message' => 'Product missing required field "code"',
                            ];

                            continue;
                        }

                        $products[$productRow['code']] = $this->mergeProduct(
                            $products[$productRow['code']] ?? null,
                            $productRow,
                        );

                        try {
                            $serviceList = $this->catalog->listServicesForProductRouting(
                                $productRow['code'],
                                $from,
                                $to,
                                $payer,
                            );
                        } catch (Throwable $e) {
                            $error = $this->classify($e, $from, $to, $payer, $productRow['code']);
                            if ($error['code'] === self::ERROR_AUTH_FAILED) {
                                throw new RuntimeException(
                                    'DHL API authentication failed (401). Refresh DHL_API_TOKEN and retry.',
                                    401,
                                );
                            }
                            if ($error['code'] !== self::ERROR_ROUTING_NOT_FOUND) {
                                $errors[] = $error;
                                $this->logger->warning('dhl.catalog.bootstrap.services_failed', $error);
                            }

                            continue;
                        }

                        foreach ($serviceList['services'] as $rawService) {
                            $serviceRow = $this->mapService($rawService);
                            if ($serviceRow === null) {
                                $errors[] = [
                                    'code' => self::ERROR_SCHEMA_INVALID,
                                    'product' => $productRow['code'],
                                    'from' => $from,
                                    'to' => $to,
                                    'payer' => $payer,
                                    'message' => 'Service missing required field "code"',
                                ];

                                continue;
                            }

                            $services[$serviceRow['code']] = $this->mergeService(
                                $services[$serviceRow['code']] ?? null,
                                $serviceRow,
                            );

                            $assignmentRow = $this->mapAssignment(
                                $productRow['code'],
                                $serviceRow['code'],
                                $from,
                                $to,
                                $payer,
                                $rawService,
                            );
                            $key = $this->assignmentKey($assignmentRow);
                            $assignments[$key] = $assignmentRow;
                        }
                    }
                }
            }
        }

        $productsList = array_values($products);
        $servicesList = array_values($services);
        $assignmentsList = array_values($assignments);

        return [
            'products' => $productsList,
            'services' => $servicesList,
            'assignments' => $assignmentsList,
            'errors' => $errors,
            'counts' => [
                'products' => count($productsList),
                'services' => count($servicesList),
                'assignments' => count($assignmentsList),
                'errors' => count($errors),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function classify(
        Throwable $e,
        string $from,
        string $to,
        string $payer,
        ?string $productCode = null,
    ): array {
        $status = null;
        if ($e instanceof RequestException) {
            $status = $e->response->status();
        }
        $code = match (true) {
            $status === 401 || $status === 403 => self::ERROR_AUTH_FAILED,
            $status === 404 => self::ERROR_ROUTING_NOT_FOUND,
            default => self::ERROR_API_UNAVAILABLE,
        };

        return [
            'code' => $code,
            'http_status' => $status,
            'from' => $from,
            'to' => $to,
            'payer' => $payer,
            'product' => $productCode,
            'message' => substr($e->getMessage(), 0, 500),
        ];
    }

    /**
     * @param  array<string,mixed>  $raw
     * @return array<string,mixed>|null
     */
    private function mapProduct(array $raw, string $from, string $to): ?array
    {
        $code = $this->pickString($raw, ['code', 'productCode', 'id']);
        if ($code === null) {
            return null;
        }

        return [
            'code' => $code,
            'name' => $this->pickString($raw, ['name', 'productName', 'title']) ?? $code,
            'description' => $this->pickString($raw, ['description', 'productDescription']) ?? '',
            'market_availability' => strtoupper(
                $this->pickString($raw, ['marketAvailability', 'market']) ?? 'BOTH',
            ),
            'from_countries' => [$from],
            'to_countries' => [$to],
            'allowed_package_types' => $this->pickStringList($raw, ['allowedPackageTypes', 'packageTypes'])
                ?? ['PLT'],
            'weight_min_kg' => (float) ($raw['weightMinKg'] ?? $raw['minWeightKg'] ?? 0.0),
            'weight_max_kg' => (float) ($raw['weightMaxKg'] ?? $raw['maxWeightKg'] ?? 2500.0),
            'dim_max_l_cm' => (float) ($raw['dimMaxLengthCm'] ?? $raw['maxLengthCm'] ?? 240.0),
            'dim_max_b_cm' => (float) ($raw['dimMaxWidthCm'] ?? $raw['maxWidthCm'] ?? 120.0),
            'dim_max_h_cm' => (float) ($raw['dimMaxHeightCm'] ?? $raw['maxHeightCm'] ?? 220.0),
            'valid_from' => $this->pickString($raw, ['validFrom']) ?? '2020-01-01T00:00:00Z',
            'valid_until' => $this->pickString($raw, ['validUntil']),
            'source' => 'seed',
            'raw' => $raw,
        ];
    }

    /**
     * Vereinigt Routings über mehrere API-Calls (DE→AT und DE→FR
     * liefern dasselbe Produkt — wir mergen `to_countries`).
     *
     * @param  array<string,mixed>|null  $existing
     * @param  array<string,mixed>  $incoming
     * @return array<string,mixed>
     */
    private function mergeProduct(?array $existing, array $incoming): array
    {
        if ($existing === null) {
            return $incoming;
        }

        $existing['from_countries'] = $this->mergeUnique(
            $existing['from_countries'] ?? [],
            $incoming['from_countries'] ?? [],
        );
        $existing['to_countries'] = $this->mergeUnique(
            $existing['to_countries'] ?? [],
            $incoming['to_countries'] ?? [],
        );

        return $existing;
    }

    /**
     * @param  array<string,mixed>  $raw
     * @return array<string,mixed>|null
     */
    private function mapService(array $raw): ?array
    {
        $code = $this->pickString($raw, ['code', 'serviceCode', 'id']);
        if ($code === null) {
            return null;
        }
        $code = strtoupper($code);

        return [
            'code' => $code,
            'name' => $this->pickString($raw, ['name', 'serviceName', 'title']) ?? $code,
            'description' => $this->pickString($raw, ['description', 'serviceDescription']) ?? '',
            'category' => strtolower(
                $this->pickString($raw, ['category', 'serviceCategory']) ?? 'special',
            ),
            'parameter_schema' => is_array($raw['parameterSchema'] ?? null)
                ? $raw['parameterSchema']
                : ['type' => 'object'],
            'source' => 'seed',
        ];
    }

    /**
     * @param  array<string,mixed>|null  $existing
     * @param  array<string,mixed>  $incoming
     * @return array<string,mixed>
     */
    private function mergeService(?array $existing, array $incoming): array
    {
        return $existing ?? $incoming;
    }

    /**
     * @param  array<string,mixed>  $raw
     * @return array<string,mixed>
     */
    private function mapAssignment(
        string $productCode,
        string $serviceCode,
        string $from,
        string $to,
        string $payer,
        array $raw,
    ): array {
        $requirement = strtolower(
            $this->pickString($raw, ['requirement', 'serviceRequirement']) ?? 'allowed',
        );

        return [
            'product_code' => $productCode,
            'service_code' => $serviceCode,
            'from_country' => $from,
            'to_country' => $to,
            'payer_code' => $payer,
            'requirement' => $requirement,
            'default_parameters' => is_array($raw['defaultParameters'] ?? null)
                ? $raw['defaultParameters']
                : [],
            'source' => 'seed',
        ];
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function assignmentKey(array $row): string
    {
        return sprintf(
            '%s|%s|%s|%s|%s',
            $row['product_code'],
            $row['service_code'],
            $row['from_country'] ?? '',
            $row['to_country'] ?? '',
            $row['payer_code'] ?? '',
        );
    }

    /**
     * @param  array<string,mixed>  $raw
     * @param  list<string>  $candidates
     */
    private function pickString(array $raw, array $candidates): ?string
    {
        foreach ($candidates as $key) {
            if (isset($raw[$key]) && is_string($raw[$key]) && $raw[$key] !== '') {
                return $raw[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string,mixed>  $raw
     * @param  list<string>  $candidates
     * @return list<string>|null
     */
    private function pickStringList(array $raw, array $candidates): ?array
    {
        foreach ($candidates as $key) {
            if (isset($raw[$key]) && is_array($raw[$key])) {
                $out = [];
                foreach ($raw[$key] as $value) {
                    if (is_string($value) && $value !== '') {
                        $out[] = $value;
                    }
                }
                if ($out !== []) {
                    return array_values(array_unique($out));
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>|array<mixed>  $a
     * @param  list<string>|array<mixed>  $b
     * @return list<string>
     */
    private function mergeUnique(array $a, array $b): array
    {
        $merged = [];
        foreach ([$a, $b] as $list) {
            foreach ($list as $value) {
                if (is_string($value) && $value !== '' && ! in_array($value, $merged, true)) {
                    $merged[] = $value;
                }
            }
        }

        return $merged;
    }
}
