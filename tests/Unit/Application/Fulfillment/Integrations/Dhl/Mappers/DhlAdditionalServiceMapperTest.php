<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fulfillment\Integrations\Dhl\Mappers;

use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlServiceOptionCollection;
use App\Application\Fulfillment\Integrations\Dhl\Exceptions\DhlCatalogNotPopulatedException;
use App\Application\Fulfillment\Integrations\Dhl\Exceptions\ForbiddenDhlServiceException;
use App\Application\Fulfillment\Integrations\Dhl\Exceptions\InvalidDhlServiceParameterException;
use App\Application\Fulfillment\Integrations\Dhl\Exceptions\MissingRequiredDhlServiceException;
use App\Application\Fulfillment\Integrations\Dhl\Exceptions\UnknownDhlServiceException;
use App\Application\Fulfillment\Integrations\Dhl\Mappers\DhlAdditionalServiceMapper;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlAdditionalService;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProductServiceAssignment;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlAdditionalServiceRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlProductServiceAssignmentRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\AuditActor;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\CountryCode;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlCatalogSource;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlServiceCategory;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlServiceRequirement;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\JsonSchema;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPayerCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\RoutingContext;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;

final class DhlAdditionalServiceMapperTest extends TestCase
{
    public function test_empty_options_return_empty_array(): void
    {
        $mapper = $this->makeMapper(
            assignments: [],
            services: [],
            strict: true,
        );

        $result = $mapper->toApiPayload(
            new DhlProductCode('AAA'),
            new RoutingContext('DE', 'AT', DhlPayerCode::DAP),
            DhlServiceOptionCollection::fromArray([]),
        );

        self::assertSame([], $result);
    }

    public function test_strict_mode_with_empty_catalog_throws(): void
    {
        $mapper = $this->makeMapper(
            assignments: [],
            services: [],
            strict: true,
        );

        $this->expectException(DhlCatalogNotPopulatedException::class);
        $mapper->toApiPayload(
            new DhlProductCode('AAA'),
            new RoutingContext('DE', 'AT', DhlPayerCode::DAP),
            DhlServiceOptionCollection::fromArray(['COD']),
        );
    }

    public function test_non_strict_with_empty_catalog_passes_through_with_warning(): void
    {
        $logger = new InMemoryLogger();
        $mapper = $this->makeMapper(
            assignments: [],
            services: [],
            strict: false,
            logger: $logger,
        );

        $result = $mapper->toApiPayload(
            new DhlProductCode('AAA'),
            new RoutingContext('DE', 'AT', DhlPayerCode::DAP),
            DhlServiceOptionCollection::fromArray([
                ['code' => 'NOT'],
                ['code' => 'COD', 'parameters' => ['amount' => 50, 'currency' => 'EUR']],
            ]),
        );

        self::assertSame(
            [
                ['code' => 'NOT'],
                ['code' => 'COD', 'amount' => 50, 'currency' => 'EUR'],
            ],
            $result,
        );
        self::assertTrue($logger->hasMessage('dhl.catalog.empty_skip'));
    }

    public function test_successful_mapping_with_parameters(): void
    {
        $codSchema = JsonSchema::fromArray([
            'type' => 'object',
            'properties' => [
                'amount' => ['type' => 'number', 'minimum' => 0],
                'currency' => ['type' => 'string', 'enum' => ['EUR', 'CHF']],
            ],
            'required' => ['amount', 'currency'],
        ]);

        $mapper = $this->makeMapper(
            assignments: [
                $this->makeAssignment('AAA', 'COD', DhlServiceRequirement::ALLOWED),
                $this->makeAssignment('AAA', 'NOT', DhlServiceRequirement::ALLOWED),
            ],
            services: [
                'COD' => $this->makeService('COD', $codSchema),
                'NOT' => $this->makeService('NOT'),
            ],
            strict: true,
        );

        $result = $mapper->toApiPayload(
            new DhlProductCode('AAA'),
            new RoutingContext('DE', 'AT', DhlPayerCode::DAP),
            DhlServiceOptionCollection::fromArray([
                ['code' => 'COD', 'parameters' => ['amount' => 50, 'currency' => 'EUR']],
                ['code' => 'NOT'],
            ]),
        );

        self::assertSame(
            [
                ['code' => 'COD', 'amount' => 50, 'currency' => 'EUR'],
                ['code' => 'NOT'],
            ],
            $result,
        );
    }

    public function test_unknown_service_code_throws(): void
    {
        $mapper = $this->makeMapper(
            assignments: [
                $this->makeAssignment('AAA', 'NOT', DhlServiceRequirement::ALLOWED),
            ],
            services: [
                'NOT' => $this->makeService('NOT'),
            ],
            strict: true,
        );

        $this->expectException(UnknownDhlServiceException::class);
        $mapper->toApiPayload(
            new DhlProductCode('AAA'),
            new RoutingContext('DE', 'AT', DhlPayerCode::DAP),
            DhlServiceOptionCollection::fromArray(['UNKNOWN']),
        );
    }

    public function test_service_known_globally_but_not_assigned_throws_forbidden(): void
    {
        // Service exists in catalog but no assignment for this product/routing.
        $mapper = $this->makeMapper(
            assignments: [
                $this->makeAssignment('AAA', 'NOT', DhlServiceRequirement::ALLOWED),
            ],
            services: [
                'NOT' => $this->makeService('NOT'),
                'COD' => $this->makeService('COD'),
            ],
            strict: true,
        );

        $this->expectException(ForbiddenDhlServiceException::class);
        $mapper->toApiPayload(
            new DhlProductCode('AAA'),
            new RoutingContext('DE', 'AT', DhlPayerCode::DAP),
            DhlServiceOptionCollection::fromArray(['COD']),
        );
    }

    public function test_forbidden_assignment_throws(): void
    {
        $mapper = $this->makeMapper(
            assignments: [
                $this->makeAssignment('AAA', 'COD', DhlServiceRequirement::FORBIDDEN),
            ],
            services: [
                'COD' => $this->makeService('COD'),
            ],
            strict: true,
        );

        $this->expectException(ForbiddenDhlServiceException::class);
        $mapper->toApiPayload(
            new DhlProductCode('AAA'),
            new RoutingContext('DE', 'CH', DhlPayerCode::DAP),
            DhlServiceOptionCollection::fromArray(['COD']),
        );
    }

    public function test_missing_required_service_throws_with_codes(): void
    {
        $mapper = $this->makeMapper(
            assignments: [
                $this->makeAssignment('AAA', 'NOT', DhlServiceRequirement::ALLOWED),
                $this->makeAssignment('AAA', 'IDC', DhlServiceRequirement::REQUIRED),
                $this->makeAssignment('AAA', 'EUR', DhlServiceRequirement::REQUIRED),
            ],
            services: [
                'NOT' => $this->makeService('NOT'),
                'IDC' => $this->makeService('IDC'),
                'EUR' => $this->makeService('EUR'),
            ],
            strict: true,
        );

        try {
            $mapper->toApiPayload(
                new DhlProductCode('AAA'),
                new RoutingContext('DE', 'AT', DhlPayerCode::DAP),
                DhlServiceOptionCollection::fromArray(['NOT']),
            );
            self::fail('Expected MissingRequiredDhlServiceException');
        } catch (MissingRequiredDhlServiceException $e) {
            self::assertSame(['EUR', 'IDC'], $e->missingCodes);
        }
    }

    public function test_invalid_parameter_throws(): void
    {
        $codSchema = JsonSchema::fromArray([
            'type' => 'object',
            'properties' => [
                'amount' => ['type' => 'number', 'minimum' => 0],
            ],
            'required' => ['amount'],
        ]);

        $mapper = $this->makeMapper(
            assignments: [
                $this->makeAssignment('AAA', 'COD', DhlServiceRequirement::ALLOWED),
            ],
            services: [
                'COD' => $this->makeService('COD', $codSchema),
            ],
            strict: true,
        );

        $this->expectException(InvalidDhlServiceParameterException::class);
        $mapper->toApiPayload(
            new DhlProductCode('AAA'),
            new RoutingContext('DE', 'AT', DhlPayerCode::DAP),
            DhlServiceOptionCollection::fromArray([
                ['code' => 'COD', 'parameters' => ['amount' => -5]],
            ]),
        );
    }

    public function test_deprecated_service_is_mapped_with_warning(): void
    {
        $logger = new InMemoryLogger();
        $mapper = $this->makeMapper(
            assignments: [
                $this->makeAssignment('AAA', 'OLD', DhlServiceRequirement::ALLOWED),
            ],
            services: [
                'OLD' => $this->makeService('OLD', deprecatedAt: new DateTimeImmutable('2025-01-01')),
            ],
            strict: true,
            logger: $logger,
        );

        $result = $mapper->toApiPayload(
            new DhlProductCode('AAA'),
            new RoutingContext('DE', 'AT', DhlPayerCode::DAP),
            DhlServiceOptionCollection::fromArray(['OLD']),
        );

        self::assertSame([['code' => 'OLD']], $result);
        self::assertTrue($logger->hasMessage('dhl.service.deprecated'));
    }

    public function test_routing_specificity_specific_beats_global(): void
    {
        // Repository simulates real resolution: it pre-filters and returns
        // the winning row per service_code already. We stage the resolved
        // outcome (specific FORBIDDEN wins over global ALLOWED).
        $mapper = $this->makeMapper(
            assignments: [
                // Specific DE→CH forbidden for COD wins
                $this->makeAssignment(
                    'AAA',
                    'COD',
                    DhlServiceRequirement::FORBIDDEN,
                    fromCountry: new CountryCode('DE'),
                    toCountry: new CountryCode('CH'),
                ),
            ],
            services: [
                'COD' => $this->makeService('COD'),
            ],
            strict: true,
        );

        $this->expectException(ForbiddenDhlServiceException::class);
        $mapper->toApiPayload(
            new DhlProductCode('AAA'),
            new RoutingContext('DE', 'CH', DhlPayerCode::DAP),
            DhlServiceOptionCollection::fromArray(['COD']),
        );
    }

    public function test_cod_parameters_render_correctly_in_payload(): void
    {
        $codSchema = JsonSchema::fromArray([
            'type' => 'object',
            'properties' => [
                'amount' => ['type' => 'number'],
                'currency' => ['type' => 'string'],
            ],
            'required' => ['amount', 'currency'],
        ]);

        $mapper = $this->makeMapper(
            assignments: [
                $this->makeAssignment('AAA', 'COD', DhlServiceRequirement::ALLOWED),
            ],
            services: [
                'COD' => $this->makeService('COD', $codSchema),
            ],
            strict: true,
        );

        $result = $mapper->toApiPayload(
            new DhlProductCode('AAA'),
            new RoutingContext('DE', 'AT', DhlPayerCode::DAP),
            DhlServiceOptionCollection::fromArray([
                ['code' => 'COD', 'parameters' => ['amount' => 50, 'currency' => 'EUR']],
            ]),
        );

        self::assertSame(
            [['code' => 'COD', 'amount' => 50, 'currency' => 'EUR']],
            $result,
        );
    }

    // ---- Helpers ---------------------------------------------------------

    /**
     * @param  list<DhlProductServiceAssignment>  $assignments
     * @param  array<string,DhlAdditionalService>  $services
     */
    private function makeMapper(
        array $assignments,
        array $services,
        bool $strict,
        ?LoggerInterface $logger = null,
    ): DhlAdditionalServiceMapper {
        return new DhlAdditionalServiceMapper(
            assignmentRepository: new InMemoryAssignmentRepository($assignments),
            serviceRepository: new InMemoryServiceRepository($services),
            logger: $logger ?? new InMemoryLogger(),
            strictValidation: $strict,
        );
    }

    private function makeAssignment(
        string $product,
        string $serviceCode,
        DhlServiceRequirement $requirement,
        ?CountryCode $fromCountry = null,
        ?CountryCode $toCountry = null,
        ?DhlPayerCode $payerCode = null,
    ): DhlProductServiceAssignment {
        return new DhlProductServiceAssignment(
            productCode: new DhlProductCode($product),
            serviceCode: $serviceCode,
            fromCountry: $fromCountry,
            toCountry: $toCountry,
            payerCode: $payerCode,
            requirement: $requirement,
            defaultParameters: [],
            source: DhlCatalogSource::SEED,
            syncedAt: null,
        );
    }

    private function makeService(
        string $code,
        ?JsonSchema $schema = null,
        ?DateTimeImmutable $deprecatedAt = null,
    ): DhlAdditionalService {
        return new DhlAdditionalService(
            code: $code,
            name: $code . ' Service',
            description: '',
            category: DhlServiceCategory::SPECIAL,
            parameterSchema: $schema ?? JsonSchema::fromArray(['type' => 'object']),
            deprecatedAt: $deprecatedAt,
            source: DhlCatalogSource::SEED,
            syncedAt: null,
        );
    }
}

/**
 * In-memory fake for the assignment repository. Only `findAllowedServicesFor`
 * is exercised by the mapper; the rest is intentionally not implemented.
 */
final class InMemoryAssignmentRepository implements DhlProductServiceAssignmentRepository
{
    /** @param list<DhlProductServiceAssignment> $assignments */
    public function __construct(private readonly array $assignments)
    {
    }

    public function findAllowedServicesFor(
        DhlProductCode $product,
        CountryCode $from,
        CountryCode $to,
        DhlPayerCode $payer,
    ): iterable {
        foreach ($this->assignments as $assignment) {
            if ($assignment->productCode()->value !== $product->value) {
                continue;
            }
            // Honor null = global semantics on each routing axis.
            if ($assignment->fromCountry() !== null && ! $assignment->fromCountry()->equals($from)) {
                continue;
            }
            if ($assignment->toCountry() !== null && ! $assignment->toCountry()->equals($to)) {
                continue;
            }
            if ($assignment->payerCode() !== null && $assignment->payerCode() !== $payer) {
                continue;
            }
            yield $assignment;
        }
    }

    public function findByProduct(DhlProductCode $product): iterable
    {
        return [];
    }

    public function save(DhlProductServiceAssignment $assignment, AuditActor $actor): void
    {
        // no-op
    }

    public function delete(DhlProductServiceAssignment $assignment, AuditActor $actor): void
    {
        // no-op
    }
}

final class InMemoryServiceRepository implements DhlAdditionalServiceRepository
{
    /** @param array<string,DhlAdditionalService> $services */
    public function __construct(private readonly array $services)
    {
    }

    public function findByCode(string $serviceCode): ?DhlAdditionalService
    {
        return $this->services[$serviceCode] ?? null;
    }

    public function findAllActive(): iterable
    {
        foreach ($this->services as $svc) {
            if (! $svc->isDeprecated()) {
                yield $svc;
            }
        }
    }

    public function findByCategory(DhlServiceCategory $category): iterable
    {
        return [];
    }

    public function save(DhlAdditionalService $service, AuditActor $actor): void
    {
        // no-op
    }

    public function softDeprecate(string $serviceCode, AuditActor $actor): void
    {
        // no-op
    }

    public function restore(string $serviceCode, AuditActor $actor): void
    {
        // no-op
    }
}

final class InMemoryLogger extends AbstractLogger
{
    /** @var list<array{level:string,message:string,context:array<string,mixed>}> */
    public array $records = [];

    /**
     * @param  array<mixed>  $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    public function hasMessage(string $needle): bool
    {
        foreach ($this->records as $r) {
            if ($r['message'] === $needle) {
                return true;
            }
        }

        return false;
    }
}
