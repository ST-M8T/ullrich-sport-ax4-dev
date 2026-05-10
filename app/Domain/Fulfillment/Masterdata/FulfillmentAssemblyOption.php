<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Masterdata;

use App\Domain\Shared\ValueObjects\Identifier;

final class FulfillmentAssemblyOption
{
    private function __construct(
        private readonly Identifier $id,
        private readonly int $assemblyItemId,
        private readonly Identifier $assemblyPackagingId,
        private readonly ?float $assemblyWeightKg,
        private readonly ?string $description,
    ) {}

    public static function hydrate(
        Identifier $id,
        int $assemblyItemId,
        Identifier $assemblyPackagingId,
        ?float $assemblyWeightKg,
        ?string $description,
    ): self {
        return new self(
            $id,
            $assemblyItemId,
            $assemblyPackagingId,
            $assemblyWeightKg !== null ? max(0.0, $assemblyWeightKg) : null,
            $description ? trim($description) : null,
        );
    }

    public function id(): Identifier
    {
        return $this->id;
    }

    public function assemblyItemId(): int
    {
        return $this->assemblyItemId;
    }

    public function assemblyPackagingId(): Identifier
    {
        return $this->assemblyPackagingId;
    }

    public function assemblyWeightKg(): ?float
    {
        return $this->assemblyWeightKg;
    }

    public function description(): ?string
    {
        return $this->description;
    }
}
