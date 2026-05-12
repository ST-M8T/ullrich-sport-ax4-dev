<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\DTOs;

use App\Domain\Fulfillment\Masterdata\FulfillmentSenderProfile;
use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Fulfillment\Orders\ShipmentPackage;

final class DhlBookingRequestDto
{
    /**
     * @param  array<string,mixed>  $payload
     */
    private function __construct(
        private readonly array $payload,
    ) {
        // Constructor exists for promoted properties only.
    }

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
    ): self {
        $packages = $order->packages();
        $packageData = [];

        foreach ($packages as $package) {
            $packageData[] = self::mapPackage($package);
        }

        $payload = [
            'productId' => $options->productId(),
            'sender' => self::mapSender($senderProfile),
            'receiver' => self::mapReceiver($order),
            'packages' => $packageData,
            'references' => self::mapReferences($order),
        ];

        $serviceCollection = $options->serviceOptions();
        if ($serviceCollection->isEmpty() === false) {
            $payload['additionalServices'] = $serviceCollection->toArray();
        }

        if ($options->pickupDate()) {
            $payload['pickupDate'] = $options->pickupDate();
        }

        return new self($payload);
    }

    /**
     * @return list<array{qualifier: string, value: string}>
     */
    private static function mapReferences(ShipmentOrder $order): array
    {
        $references = [];

        $orderRef = (string) $order->externalOrderId();
        if ($orderRef !== '') {
            $references[] = ['qualifier' => 'CNR', 'value' => substr($orderRef, 0, 35)];
        }

        $customerNumber = $order->customerNumber();
        if ($customerNumber !== null && (string) $customerNumber !== '') {
            $references[] = ['qualifier' => 'CNR', 'value' => substr((string) $customerNumber, 0, 35)];
        }

        return $references;
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

        if ($profile->addressAddition() !== null) {
            $address['addressAddition'] = $profile->addressAddition();
        }

        $sender = [
            'companyName' => $profile->companyName(),
            'address' => $address,
        ];

        if ($profile->contactPerson() !== null) {
            $sender['contactPerson'] = $profile->contactPerson();
        }

        if ($profile->email() !== null) {
            $sender['email'] = $profile->email();
        }

        if ($profile->phone() !== null) {
            $sender['phone'] = $profile->phone();
        }

        return $sender;
    }

    /**
     * @return array<string,mixed>
     */
    private static function mapReceiver(ShipmentOrder $order): array
    {
        $receiver = [
            'countryIso2' => $order->destinationCountry() ?? 'DE',
        ];

        if ($order->contactEmail() !== null) {
            $receiver['email'] = $order->contactEmail();
        }

        if ($order->contactPhone() !== null) {
            $receiver['phone'] = $order->contactPhone();
        }

        $metadata = $order->metadata();
        if (isset($metadata['receiver'])) {
            $receiverData = $metadata['receiver'];
            if (is_array($receiverData)) {
                if (isset($receiverData['companyName'])) {
                    $receiver['companyName'] = (string) $receiverData['companyName'];
                }
                if (isset($receiverData['contactPerson'])) {
                    $receiver['contactPerson'] = (string) $receiverData['contactPerson'];
                }
                if (isset($receiverData['streetName'])) {
                    $receiver['address']['streetName'] = (string) $receiverData['streetName'];
                }
                if (isset($receiverData['streetNumber'])) {
                    $receiver['address']['streetNumber'] = (string) $receiverData['streetNumber'];
                }
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

        if ($package->packageReference() !== null) {
            $packageData['reference'] = $package->packageReference();
        }

        return $packageData;
    }
}
