<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Masterdata;

use App\Domain\Shared\ValueObjects\Identifier;

final class FulfillmentFreightProfile
{
    private function __construct(
        private readonly Identifier $shippingProfileId,
        private readonly ?string $label,
    ) {}

    public static function hydrate(Identifier $shippingProfileId, ?string $label): self
    {
        return new self($shippingProfileId, $label ? trim($label) : null);
    }

    public function shippingProfileId(): Identifier
    {
        return $this->shippingProfileId;
    }

    public function label(): ?string
    {
        return $this->label;
    }
}
