<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Masterdata;

use App\Domain\Shared\ValueObjects\Identifier;
use InvalidArgumentException;

final class FulfillmentVariationProfile
{
    private const DEFAULT_STATES = ['kit', 'assembled'];

    private function __construct(
        private readonly Identifier $id,
        private readonly int $itemId,
        private readonly ?int $variationId,
        private readonly ?string $variationName,
        private readonly string $defaultState,
        private readonly Identifier $defaultPackagingId,
        private readonly ?float $defaultWeightKg,
        private readonly ?Identifier $assemblyOptionId,
    ) {}

    public static function hydrate(
        Identifier $id,
        int $itemId,
        ?int $variationId,
        ?string $variationName,
        string $defaultState,
        Identifier $defaultPackagingId,
        ?float $defaultWeightKg,
        ?Identifier $assemblyOptionId,
    ): self {
        $state = strtolower(trim($defaultState));
        if (! in_array($state, self::DEFAULT_STATES, true)) {
            throw new InvalidArgumentException("Unknown default state '{$defaultState}'.");
        }

        return new self(
            $id,
            $itemId,
            $variationId,
            $variationName ? trim($variationName) : null,
            $state,
            $defaultPackagingId,
            $defaultWeightKg !== null ? max(0.0, $defaultWeightKg) : null,
            $assemblyOptionId,
        );
    }

    public function id(): Identifier
    {
        return $this->id;
    }

    public function itemId(): int
    {
        return $this->itemId;
    }

    public function variationId(): ?int
    {
        return $this->variationId;
    }

    public function variationName(): ?string
    {
        return $this->variationName;
    }

    public function defaultState(): string
    {
        return $this->defaultState;
    }

    public function defaultPackagingId(): Identifier
    {
        return $this->defaultPackagingId;
    }

    public function defaultWeightKg(): ?float
    {
        return $this->defaultWeightKg;
    }

    public function assemblyOptionId(): ?Identifier
    {
        return $this->assemblyOptionId;
    }

    public function isDefaultKit(): bool
    {
        return $this->defaultState === 'kit';
    }

    public function isDefaultAssembled(): bool
    {
        return $this->defaultState === 'assembled';
    }
}
