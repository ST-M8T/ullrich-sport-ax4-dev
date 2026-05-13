<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Mappers;

use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlServiceOptionCollection;
use App\Application\Fulfillment\Integrations\Dhl\Exceptions\DhlCatalogNotPopulatedException;
use App\Application\Fulfillment\Integrations\Dhl\Exceptions\ForbiddenDhlServiceException;
use App\Application\Fulfillment\Integrations\Dhl\Exceptions\InvalidDhlServiceParameterException;
use App\Application\Fulfillment\Integrations\Dhl\Exceptions\MissingRequiredDhlServiceException;
use App\Application\Fulfillment\Integrations\Dhl\Exceptions\UnknownDhlServiceException;
use App\Domain\Fulfillment\Orders\ValueObjects\DhlServiceOption;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProductServiceAssignment;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions\InvalidParameterException;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlAdditionalServiceRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlProductServiceAssignmentRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\CountryCode;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlServiceRequirement;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\RoutingContext;
use Psr\Log\LoggerInterface;

/**
 * Translates a DhlServiceOptionCollection into a DHL-API-conformant payload
 * array, validating the request against the catalog (PROJ-1/2).
 *
 * Engineering-Handbuch §3-§8: Application layer; depends only on Domain
 * Repository interfaces and PSR LoggerInterface. No Eloquent, no HTTP.
 *
 * Engineering-Handbuch §61/§75: Single source of truth for the service
 * payload structure. After PROJ-3 step B/C/D, the three booking services
 * route through here instead of duplicating $services->toArray() inline.
 */
final class DhlAdditionalServiceMapper
{
    public function __construct(
        private readonly DhlProductServiceAssignmentRepository $assignmentRepository,
        private readonly DhlAdditionalServiceRepository $serviceRepository,
        private readonly LoggerInterface $logger,
        private readonly bool $strictValidation = false,
    ) {
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function toApiPayload(
        DhlProductCode $productCode,
        RoutingContext $routing,
        DhlServiceOptionCollection $options,
    ): array {
        if ($options->isEmpty()) {
            return [];
        }

        $assignments = $this->loadAssignments($productCode, $routing);

        // Catalog empty for this product/routing tuple.
        if ($assignments === []) {
            if ($this->strictValidation) {
                throw new DhlCatalogNotPopulatedException();
            }

            $this->logger->warning('dhl.catalog.empty_skip', [
                'product_code' => $productCode->value,
                'routing' => $this->describeRouting($routing),
                'option_count' => count($options->all()),
            ]);

            return $this->structuralPayload($options);
        }

        // Index assignments by service code for O(1) lookup.
        /** @var array<string,DhlProductServiceAssignment> $byCode */
        $byCode = [];
        foreach ($assignments as $assignment) {
            $byCode[$assignment->serviceCode()] = $assignment;
        }

        $payload = [];
        foreach ($options->all() as $option) {
            $payload[] = $this->mapOption($option, $productCode, $routing, $byCode);
        }

        $this->assertAllRequiredPresent($options, $byCode, $productCode, $routing);

        return $payload;
    }

    /**
     * @return list<DhlProductServiceAssignment>
     */
    private function loadAssignments(DhlProductCode $productCode, RoutingContext $routing): array
    {
        $from = $routing->fromCountry();
        $to = $routing->toCountry();
        $payer = $routing->payerCode();

        if ($from === null || $to === null || $payer === null) {
            // Repository contract requires non-null routing axes. A globally-
            // scoped lookup cannot be expressed today — treat as "no catalog
            // available" and let the strict/non-strict branch decide.
            return [];
        }

        $iter = $this->assignmentRepository->findAllowedServicesFor(
            $productCode,
            new CountryCode($from),
            new CountryCode($to),
            $payer,
        );

        $out = [];
        foreach ($iter as $assignment) {
            $out[] = $assignment;
        }

        return $out;
    }

    /**
     * @param  array<string,DhlProductServiceAssignment>  $byCode
     * @return array<string,mixed>
     */
    private function mapOption(
        DhlServiceOption $option,
        DhlProductCode $productCode,
        RoutingContext $routing,
        array $byCode,
    ): array {
        $code = $option->code();

        if (! isset($byCode[$code])) {
            // Could be (a) globally unknown service or (b) not assigned to
            // this product/routing. Disambiguate via the service repository
            // to give the caller a precise exception.
            $service = $this->serviceRepository->findByCode($code);
            if ($service === null) {
                throw new UnknownDhlServiceException($code, $productCode, $routing);
            }

            // Service exists but is not allowed for this product+routing.
            throw new ForbiddenDhlServiceException($code, $productCode, $routing);
        }

        $assignment = $byCode[$code];

        if ($assignment->requirement() === DhlServiceRequirement::FORBIDDEN) {
            throw new ForbiddenDhlServiceException($code, $productCode, $routing);
        }

        $service = $this->serviceRepository->findByCode($code);
        if ($service === null) {
            // Catalog inconsistency — assignment references a code that has
            // no service row. Surface as "unknown" for the caller.
            throw new UnknownDhlServiceException($code, $productCode, $routing);
        }

        if ($service->isDeprecated()) {
            $this->logger->warning('dhl.service.deprecated', [
                'service_code' => $code,
                'product_code' => $productCode->value,
                'routing' => $this->describeRouting($routing),
            ]);
        }

        $parameters = $option->parameters() ?? [];
        if ($parameters !== []) {
            try {
                $service->validateParameters($parameters);
            } catch (InvalidParameterException $e) {
                throw new InvalidDhlServiceParameterException(
                    serviceCode: $code,
                    parameterPath: $e->path,
                    schemaViolation: $e->reason,
                );
            }
        }

        $entry = ['code' => $code];
        foreach ($parameters as $key => $value) {
            $entry[(string) $key] = $value;
        }

        return $entry;
    }

    /**
     * @param  array<string,DhlProductServiceAssignment>  $byCode
     */
    private function assertAllRequiredPresent(
        DhlServiceOptionCollection $options,
        array $byCode,
        DhlProductCode $productCode,
        RoutingContext $routing,
    ): void {
        $providedCodes = [];
        foreach ($options->all() as $option) {
            $providedCodes[$option->code()] = true;
        }

        $missing = [];
        foreach ($byCode as $code => $assignment) {
            if ($assignment->requirement() !== DhlServiceRequirement::REQUIRED) {
                continue;
            }
            if (! isset($providedCodes[$code])) {
                $missing[] = $code;
            }
        }

        if ($missing !== []) {
            sort($missing);
            throw new MissingRequiredDhlServiceException(
                missingCodes: array_values($missing),
                productCode: $productCode,
                routing: $routing,
            );
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function structuralPayload(DhlServiceOptionCollection $options): array
    {
        $out = [];
        foreach ($options->all() as $option) {
            $entry = ['code' => $option->code()];
            foreach ($option->parameters() ?? [] as $key => $value) {
                $entry[(string) $key] = $value;
            }
            $out[] = $entry;
        }

        return $out;
    }

    private function describeRouting(RoutingContext $routing): string
    {
        return sprintf(
            '%s→%s payer=%s',
            $routing->fromCountry() ?? '*',
            $routing->toCountry() ?? '*',
            $routing->payerCode()?->value ?? '*',
        );
    }
}
