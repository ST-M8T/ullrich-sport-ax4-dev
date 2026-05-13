<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions\InvalidProductSuccessorException;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\CountryCode;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlCatalogSource;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlMarketAvailability;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DimensionLimits;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\WeightLimits;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPackageType;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Aggregate root for a DHL Freight product.
 *
 * Identity = product code. Invariants are enforced at construction (fail-fast):
 *  - validUntil (if set) must be strictly later than validFrom
 *  - fromCountries and toCountries must be non-empty
 *  - allowedPackageTypes must be non-empty
 *
 * Mutating operations (`deprecate`, `restore`, `markSynced`) emit no events
 * directly; the audit-trail is the responsibility of the application service
 * that owns the persistence transaction.
 */
final class DhlProduct
{
    /**
     * @param  list<CountryCode>     $fromCountries
     * @param  list<CountryCode>     $toCountries
     * @param  list<DhlPackageType>  $allowedPackageTypes
     */
    public function __construct(
        private readonly DhlProductCode $code,
        private string $name,
        private string $description,
        private DhlMarketAvailability $marketAvailability,
        private array $fromCountries,
        private array $toCountries,
        private array $allowedPackageTypes,
        private WeightLimits $weightLimits,
        private DimensionLimits $dimensionLimits,
        private readonly DateTimeImmutable $validFrom,
        private readonly ?DateTimeImmutable $validUntil,
        private ?DateTimeImmutable $deprecatedAt,
        private ?DhlProductCode $replacedByCode,
        private DhlCatalogSource $source,
        private ?DateTimeImmutable $syncedAt,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('DhlProduct.name must not be empty.');
        }
        if ($validUntil !== null && $validUntil <= $validFrom) {
            throw new InvalidArgumentException('DhlProduct.validUntil must be strictly later than validFrom.');
        }
        if ($fromCountries === []) {
            throw new InvalidArgumentException('DhlProduct.fromCountries must not be empty.');
        }
        if ($toCountries === []) {
            throw new InvalidArgumentException('DhlProduct.toCountries must not be empty.');
        }
        if ($allowedPackageTypes === []) {
            throw new InvalidArgumentException('DhlProduct.allowedPackageTypes must not be empty.');
        }
        if ($replacedByCode !== null && $replacedByCode->value === $code->value) {
            throw new InvalidProductSuccessorException($code);
        }
        foreach ($fromCountries as $c) {
            if (! $c instanceof CountryCode) {
                throw new InvalidArgumentException('DhlProduct.fromCountries must contain CountryCode VOs only.');
            }
        }
        foreach ($toCountries as $c) {
            if (! $c instanceof CountryCode) {
                throw new InvalidArgumentException('DhlProduct.toCountries must contain CountryCode VOs only.');
            }
        }
        foreach ($allowedPackageTypes as $p) {
            if (! $p instanceof DhlPackageType) {
                throw new InvalidArgumentException('DhlProduct.allowedPackageTypes must contain DhlPackageType VOs only.');
            }
        }
    }

    public function code(): DhlProductCode
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

    public function marketAvailability(): DhlMarketAvailability
    {
        return $this->marketAvailability;
    }

    /**
     * @return list<CountryCode>
     */
    public function fromCountries(): array
    {
        return $this->fromCountries;
    }

    /**
     * @return list<CountryCode>
     */
    public function toCountries(): array
    {
        return $this->toCountries;
    }

    /**
     * @return list<DhlPackageType>
     */
    public function allowedPackageTypes(): array
    {
        return $this->allowedPackageTypes;
    }

    public function weightLimits(): WeightLimits
    {
        return $this->weightLimits;
    }

    public function dimensionLimits(): DimensionLimits
    {
        return $this->dimensionLimits;
    }

    public function validFrom(): DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function validUntil(): ?DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function deprecatedAt(): ?DateTimeImmutable
    {
        return $this->deprecatedAt;
    }

    public function replacedByCode(): ?DhlProductCode
    {
        return $this->replacedByCode;
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

    public function isValidAt(DateTimeImmutable $moment): bool
    {
        if ($this->deprecatedAt !== null && $moment >= $this->deprecatedAt) {
            return false;
        }
        if ($moment < $this->validFrom) {
            return false;
        }
        if ($this->validUntil !== null && $moment >= $this->validUntil) {
            return false;
        }

        return true;
    }

    public function supportsRoute(CountryCode $from, CountryCode $to): bool
    {
        return $this->containsCountry($this->fromCountries, $from)
            && $this->containsCountry($this->toCountries, $to);
    }

    public function deprecate(?DhlProductCode $successor, DateTimeImmutable $at): void
    {
        if ($successor !== null && $successor->value === $this->code->value) {
            throw new InvalidProductSuccessorException($this->code);
        }
        $this->deprecatedAt = $at;
        $this->replacedByCode = $successor;
    }

    public function restore(): void
    {
        $this->deprecatedAt = null;
        $this->replacedByCode = null;
    }

    public function markSynced(DateTimeImmutable $at): void
    {
        $this->syncedAt = $at;
    }

    public function equals(self $other): bool
    {
        return $this->code->value === $other->code->value;
    }

    /**
     * @param  list<CountryCode>  $haystack
     */
    private function containsCountry(array $haystack, CountryCode $needle): bool
    {
        foreach ($haystack as $c) {
            if ($c->equals($needle)) {
                return true;
            }
        }

        return false;
    }
}
