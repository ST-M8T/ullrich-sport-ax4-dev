<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\CountryCode;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlCatalogSource;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlServiceRequirement;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\JsonSchema;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPayerCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Aggregate root: N:M relation between a product and an additional service,
 * scoped by routing (from/to country) and payer code. Each of the three
 * scoping dimensions may be NULL to indicate "global" applicability.
 *
 * Conflict resolution: the more specific assignment wins. Specificity is
 * counted in `specificity()` (0..3 — one point per non-null routing field).
 */
final class DhlProductServiceAssignment
{
    /**
     * @param  array<string,mixed>  $defaultParameters
     */
    public function __construct(
        private readonly DhlProductCode $productCode,
        private readonly string $serviceCode,
        private readonly ?CountryCode $fromCountry,
        private readonly ?CountryCode $toCountry,
        private readonly ?DhlPayerCode $payerCode,
        private DhlServiceRequirement $requirement,
        private array $defaultParameters,
        private DhlCatalogSource $source,
        private ?DateTimeImmutable $syncedAt,
    ) {
        if ($serviceCode === '') {
            throw new InvalidArgumentException('DhlProductServiceAssignment.serviceCode must not be empty.');
        }
    }

    /**
     * Factory that additionally validates `defaultParameters` against the
     * corresponding service's parameter schema. Use this whenever the service
     * entity (and therefore its schema) is available.
     *
     * @param  array<string,mixed>  $defaultParameters
     */
    public static function create(
        DhlProductCode $productCode,
        string $serviceCode,
        ?CountryCode $fromCountry,
        ?CountryCode $toCountry,
        ?DhlPayerCode $payerCode,
        DhlServiceRequirement $requirement,
        array $defaultParameters,
        DhlCatalogSource $source,
        ?DateTimeImmutable $syncedAt,
        JsonSchema $serviceSchema,
    ): self {
        if ($defaultParameters !== []) {
            $serviceSchema->validate($defaultParameters);
        }

        return new self(
            $productCode,
            $serviceCode,
            $fromCountry,
            $toCountry,
            $payerCode,
            $requirement,
            $defaultParameters,
            $source,
            $syncedAt,
        );
    }

    public function productCode(): DhlProductCode
    {
        return $this->productCode;
    }

    public function serviceCode(): string
    {
        return $this->serviceCode;
    }

    public function fromCountry(): ?CountryCode
    {
        return $this->fromCountry;
    }

    public function toCountry(): ?CountryCode
    {
        return $this->toCountry;
    }

    public function payerCode(): ?DhlPayerCode
    {
        return $this->payerCode;
    }

    public function requirement(): DhlServiceRequirement
    {
        return $this->requirement;
    }

    /**
     * @return array<string,mixed>
     */
    public function defaultParameters(): array
    {
        return $this->defaultParameters;
    }

    public function source(): DhlCatalogSource
    {
        return $this->source;
    }

    public function syncedAt(): ?DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function matches(
        DhlProductCode $product,
        CountryCode $from,
        CountryCode $to,
        DhlPayerCode $payer,
    ): bool {
        if ($this->productCode->value !== $product->value) {
            return false;
        }
        if ($this->fromCountry !== null && ! $this->fromCountry->equals($from)) {
            return false;
        }
        if ($this->toCountry !== null && ! $this->toCountry->equals($to)) {
            return false;
        }
        if ($this->payerCode !== null && $this->payerCode !== $payer) {
            return false;
        }

        return true;
    }

    /**
     * 0 = fully global; +1 for each non-null routing dimension. Max = 3.
     * Used by the repository as the tiebreaker when several assignments match
     * the same lookup.
     */
    public function specificity(): int
    {
        return ($this->fromCountry !== null ? 1 : 0)
            + ($this->toCountry !== null ? 1 : 0)
            + ($this->payerCode !== null ? 1 : 0);
    }

    public function markSynced(DateTimeImmutable $at): void
    {
        $this->syncedAt = $at;
    }
}
