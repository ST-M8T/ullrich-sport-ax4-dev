<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\DTOs;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPackageType;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPayerCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;

final class DhlBookingOptions
{
    /**
     * Constructor accepts both the legacy (string $productId, …) and the new
     * typed (DhlProductCode + DhlPayerCode + DhlPackageType) shape. New optional
     * VO fields are additive — existing callers continue to work; the new
     * payload assembler reads the typed fields when present.
     */
    /**
     * @param  array<int,array<string,mixed>>|null  $piecesOverride
     *   Form-level piece override (UI-eingegebene Packstuecke). Wenn gesetzt,
     *   ueberschreibt es die aus der ShipmentOrder hydratisierten packages().
     *   Struktur pro Eintrag (siehe DhlBookingRequest::rules()):
     *     - number_of_pieces  : int >= 1
     *     - package_type      : ?string (Fallback: defaultPackageType)
     *     - weight            : float (kg)
     *     - width/height/length: ?float (cm)
     *     - marks_and_numbers : ?string
     */
    public function __construct(
        private readonly ?string $productId,
        private readonly DhlServiceOptionCollection $serviceOptions,
        private readonly ?string $pickupDate,
        private readonly ?DhlProductCode $productCode = null,
        private readonly ?DhlPayerCode $payerCode = null,
        private readonly ?DhlPackageType $defaultPackageType = null,
        private readonly ?array $piecesOverride = null,
    ) {}

    /**
     * @param  array<string,mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $productCode = null;
        if (isset($data['product_code']) && is_string($data['product_code']) && $data['product_code'] !== '') {
            $productCode = DhlProductCode::fromString($data['product_code']);
        } elseif (isset($data['product_id']) && is_string($data['product_id']) && $data['product_id'] !== '') {
            // Best-effort upgrade: treat the legacy product_id as a product code
            // when it fits the spec (≤ 3 alnum uppercase). Otherwise stay null —
            // existing flows that still rely on the loose productId() string keep
            // working unchanged.
            $candidate = strtoupper(trim($data['product_id']));
            if (preg_match('/^[A-Z0-9]{1,3}$/', $candidate) === 1) {
                $productCode = new DhlProductCode($candidate);
            }
        }

        $payerCode = null;
        if (isset($data['payer_code']) && is_string($data['payer_code']) && $data['payer_code'] !== '') {
            $payerCode = DhlPayerCode::fromString($data['payer_code']);
        }

        $defaultPackageType = null;
        if (isset($data['default_package_type']) && is_string($data['default_package_type']) && $data['default_package_type'] !== '') {
            $defaultPackageType = DhlPackageType::fromString($data['default_package_type']);
        }

        $piecesOverride = null;
        if (isset($data['pieces']) && is_array($data['pieces']) && $data['pieces'] !== []) {
            // Nur structurell normalisieren, keine fachliche Validierung
            // (DhlPieceMapper::fromFormPiece + DhlPiece-VO erzwingen Invarianten).
            $normalized = [];
            foreach ($data['pieces'] as $piece) {
                if (is_array($piece)) {
                    $normalized[] = $piece;
                }
            }
            if ($normalized !== []) {
                $piecesOverride = $normalized;
            }
        }

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
            $productCode,
            $payerCode,
            $defaultPackageType,
            $piecesOverride,
        );
    }

    /**
     * @deprecated Use {@see productCode()} once the migration to DhlProductCode is complete.
     */
    public function productId(): ?string
    {
        return $this->productId;
    }

    public function productCode(): ?DhlProductCode
    {
        return $this->productCode;
    }

    public function payerCode(): ?DhlPayerCode
    {
        return $this->payerCode;
    }

    public function defaultPackageType(): ?DhlPackageType
    {
        return $this->defaultPackageType;
    }

    public function serviceOptions(): DhlServiceOptionCollection
    {
        return $this->serviceOptions;
    }

    public function pickupDate(): ?string
    {
        return $this->pickupDate;
    }

    /**
     * @return array<int,array<string,mixed>>|null
     */
    public function piecesOverride(): ?array
    {
        return $this->piecesOverride;
    }

    public function hasPiecesOverride(): bool
    {
        return $this->piecesOverride !== null && $this->piecesOverride !== [];
    }
}
