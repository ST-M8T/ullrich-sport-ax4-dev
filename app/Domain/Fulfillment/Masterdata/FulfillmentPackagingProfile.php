<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Masterdata;

use App\Domain\Shared\ValueObjects\Identifier;

/**
 * Domain entity representing a row in fulfillment_packaging_profiles.
 */
final class FulfillmentPackagingProfile
{
    private function __construct(
        private readonly Identifier $id,
        private readonly string $packageName,
        private readonly ?string $packagingCode,
        private readonly int $lengthMm,
        private readonly int $widthMm,
        private readonly int $heightMm,
        private readonly int $truckSlotUnits,
        private readonly int $maxUnitsPerPalletSameRecipient,
        private readonly int $maxUnitsPerPalletMixedRecipient,
        private readonly int $maxStackablePalletsSameRecipient,
        private readonly int $maxStackablePalletsMixedRecipient,
        private readonly ?string $notes,
    ) {}

    public static function hydrate(
        Identifier $id,
        string $packageName,
        ?string $packagingCode,
        int $lengthMm,
        int $widthMm,
        int $heightMm,
        int $truckSlotUnits,
        int $maxUnitsPerPalletSameRecipient,
        int $maxUnitsPerPalletMixedRecipient,
        int $maxStackablePalletsSameRecipient,
        int $maxStackablePalletsMixedRecipient,
        ?string $notes,
    ): self {
        return new self(
            $id,
            trim($packageName),
            $packagingCode ? trim($packagingCode) : null,
            max(0, $lengthMm),
            max(0, $widthMm),
            max(0, $heightMm),
            max(1, $truckSlotUnits),
            max(1, $maxUnitsPerPalletSameRecipient),
            max(1, $maxUnitsPerPalletMixedRecipient),
            max(1, $maxStackablePalletsSameRecipient),
            max(1, $maxStackablePalletsMixedRecipient),
            $notes ? trim($notes) : null,
        );
    }

    public function id(): Identifier
    {
        return $this->id;
    }

    public function packageName(): string
    {
        return $this->packageName;
    }

    public function packagingCode(): ?string
    {
        return $this->packagingCode;
    }

    public function lengthMillimetres(): int
    {
        return $this->lengthMm;
    }

    public function widthMillimetres(): int
    {
        return $this->widthMm;
    }

    public function heightMillimetres(): int
    {
        return $this->heightMm;
    }

    public function truckSlotUnits(): int
    {
        return $this->truckSlotUnits;
    }

    public function maxUnitsPerPalletSameRecipient(): int
    {
        return $this->maxUnitsPerPalletSameRecipient;
    }

    public function maxUnitsPerPalletMixedRecipient(): int
    {
        return $this->maxUnitsPerPalletMixedRecipient;
    }

    public function maxStackablePalletsSameRecipient(): int
    {
        return $this->maxStackablePalletsSameRecipient;
    }

    public function maxStackablePalletsMixedRecipient(): int
    {
        return $this->maxStackablePalletsMixedRecipient;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }
}
