<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Orders;

use App\Domain\Fulfillment\Orders\ValueObjects\PackageDimensions;
use App\Domain\Shared\ValueObjects\Identifier;

final class ShipmentPackage
{
    private function __construct(
        private readonly Identifier $id,
        private readonly Identifier $orderId,
        private readonly ?Identifier $packagingProfileId,
        private readonly ?string $packageReference,
        private readonly int $quantity,
        private readonly ?float $weightKg,
        private readonly ?PackageDimensions $dimensions,
        private readonly int $truckSlotUnits,
    ) {}

    public static function hydrate(
        Identifier $id,
        Identifier $orderId,
        ?Identifier $packagingProfileId,
        ?string $packageReference,
        int $quantity,
        ?float $weightKg,
        ?int $lengthMm,
        ?int $widthMm,
        ?int $heightMm,
        int $truckSlotUnits,
    ): self {
        return new self(
            $id,
            $orderId,
            $packagingProfileId,
            $packageReference ? trim($packageReference) : null,
            max(0, $quantity),
            $weightKg !== null ? max(0.0, $weightKg) : null,
            self::resolveDimensions($lengthMm, $widthMm, $heightMm),
            max(1, $truckSlotUnits),
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

    public function packagingProfileId(): ?Identifier
    {
        return $this->packagingProfileId;
    }

    public function packageReference(): ?string
    {
        return $this->packageReference;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function weightKg(): ?float
    {
        return $this->weightKg;
    }

    public function dimensions(): ?PackageDimensions
    {
        return $this->dimensions;
    }

    public function lengthMillimetres(): ?int
    {
        return $this->dimensions?->length();
    }

    public function widthMillimetres(): ?int
    {
        return $this->dimensions?->width();
    }

    public function heightMillimetres(): ?int
    {
        return $this->dimensions?->height();
    }

    public function truckSlotUnits(): int
    {
        return $this->truckSlotUnits;
    }

    private static function resolveDimensions(
        ?int $length,
        ?int $width,
        ?int $height,
    ): ?PackageDimensions {
        if ($length === null || $width === null || $height === null) {
            return null;
        }

        if ($length <= 0 || $width <= 0 || $height <= 0) {
            return null;
        }

        return PackageDimensions::fromMillimetres($length, $width, $height);
    }
}
