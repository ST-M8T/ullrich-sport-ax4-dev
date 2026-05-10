<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\DTOs;

use App\Domain\Fulfillment\Masterdata\FulfillmentSenderProfile;
use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Fulfillment\Orders\ShipmentPackage;

final class DhlPriceQuoteRequestDto
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

    /**
     * @param  array<string,mixed>  $options
     */
    public static function fromShipmentOrder(
        ShipmentOrder $order,
        FulfillmentSenderProfile $senderProfile,
        ?string $productId = null,
        array $options = [],
    ): self {
        $packages = $order->packages();
        $packageData = [];

        foreach ($packages as $package) {
            $packageData[] = self::mapPackage($package);
        }

        $payload = [
            'productId' => $productId ?? $options['product_id'] ?? null,
            'sender' => self::mapSender($senderProfile),
            'receiver' => self::mapReceiver($order),
            'packages' => $packageData,
        ];

        if (isset($options['additional_services']) && is_array($options['additional_services'])) {
            $payload['additionalServices'] = $options['additional_services'];
        }

        return new self($payload);
    }

    /**
     * @return array<string,mixed>
     */
    private static function mapSender(FulfillmentSenderProfile $profile): array
    {
        $address = [
            'streetName' => $profile->streetName(),
            'postalCode' => $profile->postalCode(),
            'city' => $profile->city(),
            'countryIso2' => $profile->countryIso2(),
        ];

        if ($profile->streetNumber() !== null) {
            $address['streetNumber'] = $profile->streetNumber();
        }

        return [
            'companyName' => $profile->companyName(),
            'address' => $address,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function mapReceiver(ShipmentOrder $order): array
    {
        $receiver = [
            'countryIso2' => $order->destinationCountry() ?? 'DE',
        ];

        $metadata = $order->metadata();
        if (isset($metadata['receiver']) && is_array($metadata['receiver'])) {
            $receiverData = $metadata['receiver'];
            if (isset($receiverData['postalCode'])) {
                $receiver['address']['postalCode'] = (string) $receiverData['postalCode'];
            }
            if (isset($receiverData['city'])) {
                $receiver['address']['city'] = (string) $receiverData['city'];
            }
            if (isset($receiverData['countryIso2'])) {
                $receiver['countryIso2'] = strtoupper((string) $receiverData['countryIso2']);
            }
        }

        return $receiver;
    }

    /**
     * @return array<string,mixed>
     */
    private static function mapPackage(ShipmentPackage $package): array
    {
        $packageData = [
            'quantity' => $package->quantity(),
        ];

        if ($package->weightKg() !== null) {
            $packageData['weight'] = [
                'value' => $package->weightKg(),
                'unit' => 'kg',
            ];
        }

        if ($dimensions = $package->dimensions()) {
            $packageData['dimensions'] = $dimensions->toArray();
        }

        return $packageData;
    }
}
