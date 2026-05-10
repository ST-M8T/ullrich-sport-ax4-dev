<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\DTOs;

final class DhlBookingOptions
{
    public function __construct(
        private readonly ?string $productId,
        private readonly DhlServiceOptionCollection $serviceOptions,
        private readonly ?string $pickupDate,
    ) {}

    /**
     * @param  array<string,mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['product_id']) && $data['product_id'] !== ''
                ? (string) $data['product_id']
                : null,
            ($data['additional_services'] ?? null) instanceof DhlServiceOptionCollection
                ? $data['additional_services']
                : DhlServiceOptionCollection::fromArray((array) ($data['additional_services'] ?? [])),
            isset($data['pickup_date']) && $data['pickup_date'] !== ''
                ? (string) $data['pickup_date']
                : null,
        );
    }

    public function productId(): ?string
    {
        return $this->productId;
    }

    public function serviceOptions(): DhlServiceOptionCollection
    {
        return $this->serviceOptions;
    }

    public function pickupDate(): ?string
    {
        return $this->pickupDate;
    }
}
