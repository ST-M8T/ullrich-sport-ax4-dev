<?php

namespace App\Application\Fulfillment\Orders;

use App\Domain\Fulfillment\Orders\ShipmentOrderItem;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Reine Mapping-Verantwortung: Plenty-API-Payload (unsicher) → strukturierte
 * Felder fuer ShipmentOrder-Hydration. Kein Persistierungs-, kein Event-Code.
 *
 * Defensive Extraktion gemaess Engineering-Handbuch §27 (Import Regel):
 * externe Daten gelten als unsicher.
 */
final class PlentyOrderMapper
{
    /**
     * DHL "Reference" / "ShipperReference"-Feld erlaubt maximal 35 Zeichen.
     * Wir trunkieren defensiv, damit ein zu langer Plenty-contactId-Wert
     * keinen DHL-Booking-Fehler ausloest, wenn der Wert spaeter als String
     * in eine DHL-Reference gemappt wird.
     */
    public const CUSTOMER_NUMBER_MAX_LENGTH = 35;

    /**
     * Liefert den externen Plenty-Auftrags-Identifier.
     *
     * @param  array<string, mixed>  $plentyOrder
     *
     * @throws InvalidArgumentException wenn keine gueltige id im Payload steht.
     */
    public function extractExternalOrderId(array $plentyOrder): int
    {
        $rawId = $plentyOrder['id'] ?? null;
        if ($rawId === null || $rawId === '') {
            throw new InvalidArgumentException('Plenty-Auftrag enthaelt keine id.');
        }

        $orderId = (int) $rawId;
        if ($orderId <= 0) {
            throw new InvalidArgumentException('Plenty-Auftrags-id ist nicht positiv.');
        }

        return $orderId;
    }

    /**
     * Extrahiert die Kundennummer (customerNumber) aus dem Plenty-Payload.
     *
     * Quelle: $plentyOrder['billingAddress']['contactId'] — die top-level
     * 'contactId' liefert Plenty fuer diesen Endpoint nicht zuverlaessig.
     * Defensives Fallback-Verhalten: null wenn nicht vorhanden / leer.
     *
     * @param  array<string, mixed>  $plentyOrder
     */
    public function extractCustomerNumber(array $plentyOrder): ?string
    {
        $billingAddress = $plentyOrder['billingAddress'] ?? null;
        if (! is_array($billingAddress)) {
            return null;
        }

        $contactId = $billingAddress['contactId'] ?? null;
        if ($contactId === null) {
            return null;
        }

        $value = trim((string) $contactId);
        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        if (strlen($value) > self::CUSTOMER_NUMBER_MAX_LENGTH) {
            $value = substr($value, 0, self::CUSTOMER_NUMBER_MAX_LENGTH);
        }

        return $value;
    }

    /**
     * Mappt das Plenty-Payload in strukturierte Felder fuer die ShipmentOrder-
     * Hydration. Persistierungs- und Event-Verantwortung bleibt im Service.
     *
     * @param  array<string, mixed>  $plentyOrder
     * @return array{
     *     externalOrderId:int,
     *     customerNumber:?string,
     *     customerNumberAsInt:?int,
     *     plentyId:?int,
     *     freightProfileId:?int,
     *     type:?string,
     *     senderCompany:?string,
     *     contactEmail:?string,
     *     contactPhone:?string,
     *     destinationCountry:?string,
     *     currency:?string,
     *     totalAmount:?float,
     *     status:?string,
     *     isBooked:bool,
     *     bookedBy:?string,
     *     processedAt:?DateTimeImmutable,
     *     bookedAt:?DateTimeImmutable,
     *     shippedAt:?DateTimeImmutable,
     *     createdAt:?DateTimeImmutable,
     *     updatedAt:?DateTimeImmutable,
     *     raw:array<string, mixed>
     * }
     */
    public function mapToOrderData(array $plentyOrder): array
    {
        $customerNumber = $this->extractCustomerNumber($plentyOrder);

        return [
            'externalOrderId' => $this->extractExternalOrderId($plentyOrder),
            'customerNumber' => $customerNumber,
            'customerNumberAsInt' => $customerNumber !== null ? (int) $customerNumber : null,
            'plentyId' => isset($plentyOrder['plentyId']) ? (int) $plentyOrder['plentyId'] : null,
            'freightProfileId' => $this->extractFreightProfileId($plentyOrder),
            'type' => isset($plentyOrder['type']) ? (string) $plentyOrder['type'] : null,
            'senderCompany' => $plentyOrder['sender']['company'] ?? null,
            'contactEmail' => $plentyOrder['receiver']['email'] ?? null,
            'contactPhone' => $plentyOrder['receiver']['phone'] ?? null,
            'destinationCountry' => $plentyOrder['receiver']['country'] ?? null,
            'currency' => $plentyOrder['currency'] ?? null,
            'totalAmount' => isset($plentyOrder['amounts'][0]['grossTotal'])
                ? (float) $plentyOrder['amounts'][0]['grossTotal']
                : null,
            'status' => isset($plentyOrder['status']) ? (string) $plentyOrder['status'] : null,
            'isBooked' => ($plentyOrder['status'] ?? '') === 'BOOKED',
            'bookedBy' => $plentyOrder['bookedBy'] ?? null,
            'processedAt' => $this->parseDate($plentyOrder['processedAt'] ?? null),
            'bookedAt' => $this->parseDate($plentyOrder['bookedAt'] ?? null),
            'shippedAt' => $this->parseDate($plentyOrder['shippedAt'] ?? null),
            'createdAt' => $this->parseDate($plentyOrder['createdAt'] ?? null),
            'updatedAt' => $this->parseDate($plentyOrder['updatedAt'] ?? null),
            'raw' => $plentyOrder,
        ];
    }

    /**
     * Mappt Plenty-orderItems in ShipmentOrderItem-Objekte.
     *
     * @param  array<string, mixed>  $plentyOrder
     * @return array<int,ShipmentOrderItem>
     */
    public function mapItems(array $plentyOrder, Identifier $orderIdentifier): array
    {
        $items = [];
        $rawItems = $plentyOrder['orderItems'] ?? [];
        if (! is_array($rawItems)) {
            return $items;
        }

        foreach ($rawItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemId = (int) ($item['id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $items[] = ShipmentOrderItem::hydrate(
                Identifier::fromInt($itemId),
                $orderIdentifier,
                $item['itemId'] ?? null,
                $item['variationId'] ?? null,
                $item['sku'] ?? null,
                $item['text'] ?? null,
                (int) ($item['quantity'] ?? 1),
                null,
                null,
                false,
            );
        }

        return $items;
    }

    /**
     * Extrahiert die Plenty-Versandprofil-ID. Plenty liefert sie als
     * top-level `shippingProfileId` aus. Diese ID ist per Design IDENTISCH
     * mit {@see FulfillmentFreightProfile::shippingProfileId()} (siehe
     * Tabelle `fulfillment_freight_profiles.shipping_profile_id` als PK).
     *
     * Liefert null, wenn Plenty keinen oder einen ungueltigen Wert sendet —
     * der DhlSettingsResolver faellt dann auf den System-Default zurueck.
     *
     * @param  array<string, mixed>  $plentyOrder
     */
    private function extractFreightProfileId(array $plentyOrder): ?int
    {
        $raw = $plentyOrder['shippingProfileId'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        $value = (int) $raw;

        return $value > 0 ? $value : null;
    }

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        if (! $value) {
            return null;
        }

        return new DateTimeImmutable($value);
    }
}
