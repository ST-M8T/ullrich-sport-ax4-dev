<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Orders\Packaging;

use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentPackagingProfileRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentVariationProfileRepository;
use App\Domain\Fulfillment\Masterdata\FulfillmentVariationProfile;
use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Fulfillment\Orders\ShipmentOrderItem;
use App\Domain\Fulfillment\Orders\ShipmentPackage;
use App\Domain\Shared\ValueObjects\Identifier;

/**
 * Calculates {@see ShipmentPackage} instances for a {@see ShipmentOrder}
 * based on its items and the configured masterdata
 * (FulfillmentVariationProfile + FulfillmentPackagingProfile).
 *
 * Algorithmus pro Item:
 *   1. Suche VariationProfile per item_id (heuristic: erstes Profil, falls
 *      mehrere Variationen existieren).
 *   2. Hole verknüpftes PackagingProfile.
 *   3. Berechne Anzahl Pakete = ceil(quantity / maxUnitsPerPalletSameRecipient).
 *   4. Erzeuge pro Paket eine ShipmentPackage mit Maßen aus PackagingProfile
 *      und Gewicht = defaultWeightKg × Stückzahl_im_Paket.
 *
 * Engineering-Handbuch:
 *  - §5 Application orchestriert; §11 Repository-Pattern.
 *  - §62 KISS: ein klar verständlicher Algorithmus.
 *  - §15 Validierung: keine fachliche Validierung hier — VOs werfen.
 */
class OrderPackageCalculator
{
    public function __construct(
        private readonly FulfillmentVariationProfileRepository $variations,
        private readonly FulfillmentPackagingProfileRepository $packagings,
    ) {}

    /**
     * @return array<int, ShipmentPackage>
     */
    public function calculate(ShipmentOrder $order): array
    {
        $packages = [];

        foreach ($order->items() as $item) {
            $itemPackages = $this->packagesForItem($order->id(), $item);
            foreach ($itemPackages as $package) {
                $packages[] = $package;
            }
        }

        return $packages;
    }

    /**
     * @return array<int, ShipmentPackage>
     */
    private function packagesForItem(Identifier $orderId, ShipmentOrderItem $item): array
    {
        $itemId = $item->itemId();
        if ($itemId === null || $item->quantity() < 1) {
            return [];
        }

        $variation = $this->resolveVariationProfile($itemId);
        if ($variation === null) {
            return [];
        }

        $packaging = $this->packagings->getById($variation->defaultPackagingId());
        if ($packaging === null) {
            return [];
        }

        $maxPerPallet = max(1, $packaging->maxUnitsPerPalletSameRecipient());
        $unitsRemaining = $item->quantity();
        $unitWeightKg = $variation->defaultWeightKg();
        $reference = $item->sku() ?? $item->description();

        $packages = [];
        while ($unitsRemaining > 0) {
            $unitsThisPallet = min($maxPerPallet, $unitsRemaining);
            $packageWeight = $unitWeightKg !== null
                ? round($unitWeightKg * $unitsThisPallet, 2)
                : null;

            $packages[] = ShipmentPackage::hydrate(
                Identifier::placeholder(),
                $orderId,
                $variation->defaultPackagingId(),
                $reference,
                $unitsThisPallet,
                $packageWeight,
                $packaging->lengthMillimetres(),
                $packaging->widthMillimetres(),
                $packaging->heightMillimetres(),
                $packaging->truckSlotUnits(),
            );

            $unitsRemaining -= $unitsThisPallet;
        }

        return $packages;
    }

    private function resolveVariationProfile(int $itemId): ?FulfillmentVariationProfile
    {
        $found = $this->variations->findByItemId($itemId);
        foreach ($found as $profile) {
            return $profile;
        }

        return null;
    }
}
