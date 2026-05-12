<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\DTOs;

use App\Application\Fulfillment\Integrations\Dhl\Mappers\DhlPayloadAssembler;
use App\Application\Fulfillment\Integrations\Dhl\Settings\DhlSettingsResolver;
use App\Domain\Fulfillment\Masterdata\FulfillmentSenderProfile;
use App\Domain\Fulfillment\Orders\ShipmentOrder;

/**
 * Thin transport wrapper around the v2 DHL Freight booking payload produced by
 * {@see DhlPayloadAssembler}. Single source of truth — no payload logic lives
 * in this DTO (DDD §61, §70).
 */
final class DhlBookingRequestDto
{
    /**
     * @param  array<string,mixed>  $payload
     */
    private function __construct(
        private readonly array $payload,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->payload;
    }

    public static function fromShipmentOrder(
        ShipmentOrder $order,
        FulfillmentSenderProfile $senderProfile,
        DhlBookingOptions $options,
        DhlSettingsResolver $resolver,
        ?int $freightProfileId = null,
    ): self {
        $payload = DhlPayloadAssembler::buildBookingPayload(
            $order,
            $senderProfile,
            $options,
            $resolver,
            $freightProfileId,
        );

        return new self($payload);
    }
}
