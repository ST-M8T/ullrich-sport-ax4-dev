<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlCatalogSource;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlServiceCategory;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\JsonSchema;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Aggregate root for a DHL additional service (e.g. "COD", "Notification SMS").
 *
 * Identity = service code (string, 1..8 chars, uppercase alphanumeric). We
 * deliberately do not wrap the code in a VO: service codes are far less
 * semantically rich than product codes and DHL is known to occasionally extend
 * them. The hard format check stays here.
 */
final class DhlAdditionalService
{
    private const CODE_MAX_LENGTH = 8;

    public function __construct(
        private readonly string $code,
        private string $name,
        private string $description,
        private DhlServiceCategory $category,
        private JsonSchema $parameterSchema,
        private ?DateTimeImmutable $deprecatedAt,
        private DhlCatalogSource $source,
        private ?DateTimeImmutable $syncedAt,
    ) {
        if ($code === '') {
            throw new InvalidArgumentException('DhlAdditionalService.code must not be empty.');
        }
        if (mb_strlen($code) > self::CODE_MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'DhlAdditionalService.code must be at most %d chars (got %d).',
                self::CODE_MAX_LENGTH,
                mb_strlen($code),
            ));
        }
        if ($code !== strtoupper($code) || ! ctype_alnum($code)) {
            throw new InvalidArgumentException('DhlAdditionalService.code must be uppercase alphanumeric.');
        }
        if ($name === '') {
            throw new InvalidArgumentException('DhlAdditionalService.name must not be empty.');
        }
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function category(): DhlServiceCategory
    {
        return $this->category;
    }

    public function parameterSchema(): JsonSchema
    {
        return $this->parameterSchema;
    }

    public function deprecatedAt(): ?DateTimeImmutable
    {
        return $this->deprecatedAt;
    }

    public function source(): DhlCatalogSource
    {
        return $this->source;
    }

    public function syncedAt(): ?DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function isDeprecated(): bool
    {
        return $this->deprecatedAt !== null;
    }

    /**
     * Validate a parameter set against this service's schema. Throws
     * InvalidParameterException on the first violation.
     *
     * @param  array<string,mixed>  $parameters
     */
    public function validateParameters(array $parameters): void
    {
        $this->parameterSchema->validate($parameters);
    }

    public function deprecate(DateTimeImmutable $at): void
    {
        $this->deprecatedAt = $at;
    }

    public function restore(): void
    {
        $this->deprecatedAt = null;
    }

    public function markSynced(DateTimeImmutable $at): void
    {
        $this->syncedAt = $at;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }
}
