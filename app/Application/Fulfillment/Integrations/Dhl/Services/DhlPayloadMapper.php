<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Services;

use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlBookingOptions;
use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlBookingRequestDto;
use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlLabelRequestDto;
use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlPriceQuoteRequestDto;
use App\Domain\Fulfillment\Masterdata\FulfillmentSenderProfile;
use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Fulfillment\Orders\ShipmentPackage;
use App\Domain\Fulfillment\Orders\ValueObjects\ChargeableWeight;
use App\Domain\Fulfillment\Orders\ValueObjects\PackageDimensions;

final class DhlPayloadMapper
{
    public function __construct(
        private readonly float $volumetricWeightFactor = 250.0,
    ) {
        // Mapper uses the configured volumetric factor.
    }

    /**
     * @return array<string, mixed>
     */
    public function mapToBookingPayload(
        ShipmentOrder $order,
        FulfillmentSenderProfile $senderProfile,
        DhlBookingOptions $options,
    ): array {
        $dto = DhlBookingRequestDto::fromShipmentOrder(
            $order,
            $senderProfile,
            $options
        );

        return $dto->toArray();
    }

    /**
     * @param  array<string,mixed>  $options
     * @return array<string,mixed>
     */
    public function mapToLabelRequest(string $shipmentId, array $options = []): array
    {
        $dto = new DhlLabelRequestDto($shipmentId, $options);

        return $dto->toArray();
    }

    /**
     * @param  array<string,mixed>  $options
     * @return array<string,mixed>
     */
    public function mapToPriceQuoteRequest(
        ShipmentOrder $order,
        FulfillmentSenderProfile $senderProfile,
        ?string $productId = null,
        array $options = [],
    ): array {
        $dto = DhlPriceQuoteRequestDto::fromShipmentOrder($order, $senderProfile, $productId, $options);

        return $dto->toArray();
    }

    /**
     * Berechnet das Volumengewicht aus Länge, Breite und Höhe.
     * Formel: (Länge × Breite × Höhe) / Faktor
     * Standard-Faktor für DHL: 250.0 (kg/m³)
     */
    public function calculateVolumetricWeightFromDimensions(
        PackageDimensions $dimensions,
        ?float $factor = null,
    ): float {
        $factor = $factor ?? $this->volumetricWeightFactor;

        $volumeCm3 = ($dimensions->length() * $dimensions->width() * $dimensions->height()) / 1000.0;
        $volumetricWeight = $volumeCm3 / $factor;

        return round($volumetricWeight, 3);
    }

    /**
     * Berechnet das Volumengewicht für ein Paket.
     * Gibt null zurück, wenn die Dimensionen nicht vollständig sind.
     */
    public function calculateVolumetricWeightForPackage(ShipmentPackage $package): ?float
    {
        $dimensions = $package->dimensions();

        if ($dimensions === null) {
            return null;
        }

        return $this->calculateVolumetricWeightFromDimensions($dimensions);
    }

    /**
     * Bestimmt das zu verwendende Gewicht (tatsächliches Gewicht oder Volumengewicht, je nachdem was höher ist).
     */
    public function determineChargeableWeight(ShipmentPackage $package): ChargeableWeight
    {
        return ChargeableWeight::fromWeights(
            $package->weightKg(),
            $this->calculateVolumetricWeightForPackage($package),
        );
    }
}
