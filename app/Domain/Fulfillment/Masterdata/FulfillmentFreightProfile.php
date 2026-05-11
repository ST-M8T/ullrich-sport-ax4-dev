<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Masterdata;

use App\Domain\Shared\ValueObjects\Identifier;

final class FulfillmentFreightProfile
{
    /**
     * @param  array<int, string>  $dhlDefaultServiceCodes
     * @param  array<string, array{product_id: string, service_codes?: array<int, string>}>  $shippingMethodMapping
     */
    private function __construct(
        private readonly Identifier $shippingProfileId,
        private readonly ?string $label,
        private readonly ?string $dhlProductId,
        private readonly ?array $dhlDefaultServiceCodes,
        private readonly ?array $shippingMethodMapping,
        private readonly ?string $accountNumber,
    ) {}

    /**
     * @param  array<int, string>|null  $dhlDefaultServiceCodes
     * @param  array<string, array{product_id: string, service_codes?: array<int, string>}>|null  $shippingMethodMapping
     */
    public static function hydrate(
        Identifier $shippingProfileId,
        ?string $label,
        ?string $dhlProductId = null,
        ?array $dhlDefaultServiceCodes = null,
        ?array $shippingMethodMapping = null,
        ?string $accountNumber = null,
    ): self {
        return new self(
            $shippingProfileId,
            $label ? trim($label) : null,
            $dhlProductId,
            $dhlDefaultServiceCodes,
            $shippingMethodMapping,
            $accountNumber ? trim($accountNumber) : null,
        );
    }

    public function shippingProfileId(): Identifier
    {
        return $this->shippingProfileId;
    }

    public function label(): ?string
    {
        return $this->label;
    }

    public function dhlProductId(): ?string
    {
        return $this->dhlProductId;
    }

    /**
     * @return array<int, string>
     */
    public function dhlDefaultServiceCodes(): array
    {
        return $this->dhlDefaultServiceCodes ?? [];
    }

    /**
     * @return array<string, array{product_id: string, service_codes?: array<int, string>}>
     */
    public function shippingMethodMapping(): array
    {
        return $this->shippingMethodMapping ?? [];
    }

    public function accountNumber(): ?string
    {
        return $this->accountNumber;
    }
}
