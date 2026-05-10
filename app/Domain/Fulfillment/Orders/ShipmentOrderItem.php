<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Orders;

use App\Domain\Shared\ValueObjects\Identifier;

final class ShipmentOrderItem
{
    private function __construct(
        private readonly Identifier $id,
        private readonly Identifier $orderId,
        private readonly ?int $itemId,
        private readonly ?int $variationId,
        private readonly ?string $sku,
        private readonly ?string $description,
        private readonly int $quantity,
        private readonly ?Identifier $packagingProfileId,
        private readonly ?float $weightKg,
        private readonly bool $isAssembly,
    ) {}

    public static function hydrate(
        Identifier $id,
        Identifier $orderId,
        ?int $itemId,
        ?int $variationId,
        ?string $sku,
        ?string $description,
        int $quantity,
        ?Identifier $packagingProfileId,
        ?float $weightKg,
        bool $isAssembly,
    ): self {
        return new self(
            $id,
            $orderId,
            $itemId,
            $variationId,
            $sku ? trim($sku) : null,
            $description ? trim($description) : null,
            max(0, $quantity),
            $packagingProfileId,
            $weightKg !== null ? max(0.0, $weightKg) : null,
            $isAssembly,
        );
    }

    public function id(): Identifier
    {
        return $this->id;
    }

    public function orderId(): Identifier
    {
        return $this->orderId;
    }

    public function itemId(): ?int
    {
        return $this->itemId;
    }

    public function variationId(): ?int
    {
        return $this->variationId;
    }

    public function sku(): ?string
    {
        return $this->sku;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function packagingProfileId(): ?Identifier
    {
        return $this->packagingProfileId;
    }

    public function weightKg(): ?float
    {
        return $this->weightKg;
    }

    public function isAssembly(): bool
    {
        return $this->isAssembly;
    }
}
