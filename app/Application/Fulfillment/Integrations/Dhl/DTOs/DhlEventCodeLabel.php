<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\DTOs;

/**
 * DHL Event-Code -> German Label Mapping
 *
 * @see https://www.dhl.com/de-en/home/business/shipping/shipping-advice/tracking-code-meanings.html
 */
final class DhlEventCodeLabel
{
    /** @var array<string, string> */
    private const array LABELS = [
        // Pickup & Drop-off
        'SHIPMENT_PICKED_UP' => 'Sendung abgeholt',
        'PICKED_UP' => 'Sendung abgeholt',
        'DROPPED_OFF' => 'Sendung eingeliefert',

        // Transit
        'IN_TRANSIT' => 'In Transit',
        'TRANSIT' => 'Unterwegs',
        'DEPARTED_FACILITY' => 'Verlässt Logistikzentrum',
        'ARRIVED_AT_FACILITY' => 'Im Logistikzentrum angekommen',
        'PROCESSING_AT_FACILITY' => 'Verarbeitung im Logistikzentrum',
        'CUSTOMS_PENDING' => 'Zollabfertigung ausstehend',
        'CUSTOMS_PROCESSING' => 'Zollabfertigung läuft',
        'CUSTOMS_COMPLETED' => 'Zollabfertigung abgeschlossen',

        // Delivery attempts
        'OUT_FOR_DELIVERY' => 'Wird heute zugestellt',
        'DELIVERY_ATTEMPTED' => 'Zustellversuch fehlgeschlagen',
        'NO_DELIVERY_ATTEMPT' => 'Keine Zustellung erfolgt',
        'DELIVERY_RESCHEDULED' => 'Zustellung neu geplant',

        // Delivered
        'DELIVERED' => 'Zugestellt',
        'DELIVERED_TO_RECIPIENT' => 'Zugestellt',
        'SIGNED' => 'Zugestellt und unterschrieben',
        'RECEIVED_BY_RECIPIENT' => 'Empfangen',

        // Returns / Exceptions
        'RETURNED_TO_SHIPPER' => 'Rücksendung an Absender',
        'RETURN_TO_ORIGIN' => 'Rückführung an Absender',
        'HELD_AT_CUSTOMS' => 'Im Zoll aufbewahrt',
        'EXCEPTION' => 'Ausnahme',
        'DAMAGED' => 'Beschädigt',
        'LOST' => 'Sendung verloren',

        // Proof
        'PROOF_OF_DELIVERY' => 'Zustellnachweis',
        'SIGNATURE_PROOF' => 'Unterschriftsnachweis',

        // Internal
        'MANUAL_SYNC' => 'Manuelle Synchronisierung',
        'SYNC_TRIGGERED' => 'Synchronisierung ausgelöst',
        'CREATED' => 'Sendung erstellt',
        'LABEL_CREATED' => 'Label erstellt',
        'BOOKED' => 'Versand gebucht',
    ];

    public static function label(string $eventCode): string
    {
        return self::LABELS[$eventCode] ?? $eventCode;
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::LABELS;
    }
}