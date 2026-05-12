<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Orders;

use App\Domain\Fulfillment\Orders\ValueObjects\ShipmentReceiverAddress;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use InvalidArgumentException;

final class ShipmentOrder
{
    /**
     * @param  array<int,ShipmentOrderItem>  $items
     * @param  array<int,ShipmentPackage>  $packages
     * @param  array<int,string>  $trackingNumbers
     * @param  array<string,mixed>  $metadata
     * @param  array<string,mixed>  $dhlBookingPayload
     * @param  array<string,mixed>  $dhlBookingResponse
     */
    private function __construct(
        private readonly Identifier $id,
        private readonly int $externalOrderId,
        private readonly ?int $customerNumber,
        private readonly ?int $plentyOrderId,
        private readonly ?string $orderType,
        private readonly ?Identifier $senderProfileId,
        private readonly ?string $senderCode,
        private readonly ?string $contactEmail,
        private readonly ?string $contactPhone,
        private readonly ?string $destinationCountry,
        private readonly string $currency,
        private readonly ?float $totalAmount,
        private readonly ?DateTimeImmutable $processedAt,
        private readonly bool $isBooked,
        private readonly ?DateTimeImmutable $bookedAt,
        private readonly ?string $bookedBy,
        private readonly ?DateTimeImmutable $shippedAt,
        private readonly ?string $lastExportFilename,
        private readonly array $items,
        private readonly array $packages,
        private readonly array $trackingNumbers,
        private readonly array $metadata,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
        private readonly ?string $dhlShipmentId = null,
        private readonly ?string $dhlLabelUrl = null,
        private readonly ?string $dhlLabelPdfBase64 = null,
        private readonly ?string $dhlPickupReference = null,
        private readonly ?string $dhlProductId = null,
        private readonly array $dhlBookingPayload = [],
        private readonly array $dhlBookingResponse = [],
        private readonly ?string $dhlBookingError = null,
        private readonly ?DateTimeImmutable $dhlBookedAt = null,
        private readonly ?string $dhlCancelledAt = null,
        private readonly ?string $dhlCancelledBy = null,
        private readonly ?string $dhlCancellationReason = null,
        private readonly ?ShipmentReceiverAddress $receiverAddress = null,
        private readonly ?int $freightProfileId = null,
    ) {}

    /**
     * @param  array<int,ShipmentOrderItem>  $items
     * @param  array<int,ShipmentPackage>  $packages
     * @param  array<int,string>  $trackingNumbers
     * @param  array<string,mixed>  $metadata
     * @param  array<string,mixed>  $dhlBookingPayload
     * @param  array<string,mixed>  $dhlBookingResponse
     */
    public static function hydrate(
        Identifier $id,
        int $externalOrderId,
        ?int $customerNumber,
        ?int $plentyOrderId,
        ?string $orderType,
        ?Identifier $senderProfileId,
        ?string $senderCode,
        ?string $contactEmail,
        ?string $contactPhone,
        ?string $destinationCountry,
        ?string $currency,
        ?float $totalAmount,
        ?DateTimeImmutable $processedAt,
        bool $isBooked,
        ?DateTimeImmutable $bookedAt,
        ?string $bookedBy,
        ?DateTimeImmutable $shippedAt,
        ?string $lastExportFilename,
        array $items,
        array $packages,
        array $trackingNumbers,
        array $metadata,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?string $dhlShipmentId = null,
        ?string $dhlLabelUrl = null,
        ?string $dhlLabelPdfBase64 = null,
        ?string $dhlPickupReference = null,
        ?string $dhlProductId = null,
        array $dhlBookingPayload = [],
        array $dhlBookingResponse = [],
        ?string $dhlBookingError = null,
        ?DateTimeImmutable $dhlBookedAt = null,
        ?string $dhlCancelledAt = null,
        ?string $dhlCancelledBy = null,
        ?string $dhlCancellationReason = null,
        ?ShipmentReceiverAddress $receiverAddress = null,
        ?int $freightProfileId = null,
    ): self {
        return new self(
            $id,
            $externalOrderId,
            $customerNumber,
            $plentyOrderId,
            $orderType ? trim($orderType) : null,
            $senderProfileId,
            $senderCode ? trim($senderCode) : null,
            $contactEmail ? trim($contactEmail) : null,
            $contactPhone ? trim($contactPhone) : null,
            $destinationCountry ? strtoupper(trim($destinationCountry)) : null,
            $currency ? strtoupper(trim($currency)) : 'EUR',
            $totalAmount !== null ? round($totalAmount, 2) : null,
            $processedAt,
            $isBooked,
            $bookedAt,
            $bookedBy ? trim($bookedBy) : null,
            $shippedAt,
            $lastExportFilename ? trim($lastExportFilename) : null,
            $items,
            $packages,
            array_values(array_unique(array_filter(array_map('trim', $trackingNumbers)))),
            $metadata,
            $createdAt,
            $updatedAt,
            $dhlShipmentId ? trim($dhlShipmentId) : null,
            $dhlLabelUrl ? trim($dhlLabelUrl) : null,
            $dhlLabelPdfBase64 ? trim($dhlLabelPdfBase64) : null,
            $dhlPickupReference ? trim($dhlPickupReference) : null,
            $dhlProductId ? trim($dhlProductId) : null,
            $dhlBookingPayload,
            $dhlBookingResponse,
            $dhlBookingError ? trim($dhlBookingError) : null,
            $dhlBookedAt,
            $dhlCancelledAt ? trim($dhlCancelledAt) : null,
            $dhlCancelledBy ? trim($dhlCancelledBy) : null,
            $dhlCancellationReason ? trim($dhlCancellationReason) : null,
            $receiverAddress,
            $freightProfileId !== null && $freightProfileId > 0 ? $freightProfileId : null,
        );
    }

    public function id(): Identifier
    {
        return $this->id;
    }

    public function externalOrderId(): int
    {
        return $this->externalOrderId;
    }

    public function customerNumber(): ?int
    {
        return $this->customerNumber;
    }

    public function plentyOrderId(): ?int
    {
        return $this->plentyOrderId;
    }

    public function orderType(): ?string
    {
        return $this->orderType;
    }

    public function senderProfileId(): ?Identifier
    {
        return $this->senderProfileId;
    }

    public function senderCode(): ?string
    {
        return $this->senderCode;
    }

    public function assignSenderProfile(
        Identifier $senderProfileId,
        string $senderCode,
        ?DateTimeImmutable $updatedAt = null
    ): self {
        $senderCode = trim($senderCode);

        if ($senderCode === '') {
            throw new InvalidArgumentException('Sender code must not be empty.');
        }

        if ($this->dhlShipmentId !== null) {
            throw new InvalidArgumentException('Sender profile cannot be changed after DHL booking.');
        }

        return new self(
            $this->id,
            $this->externalOrderId,
            $this->customerNumber,
            $this->plentyOrderId,
            $this->orderType,
            $senderProfileId,
            $senderCode,
            $this->contactEmail,
            $this->contactPhone,
            $this->destinationCountry,
            $this->currency,
            $this->totalAmount,
            $this->processedAt,
            $this->isBooked,
            $this->bookedAt,
            $this->bookedBy,
            $this->shippedAt,
            $this->lastExportFilename,
            $this->items,
            $this->packages,
            $this->trackingNumbers,
            $this->metadata,
            $this->createdAt,
            $updatedAt ?? new DateTimeImmutable,
            $this->dhlShipmentId,
            $this->dhlLabelUrl,
            $this->dhlLabelPdfBase64,
            $this->dhlPickupReference,
            $this->dhlProductId,
            $this->dhlBookingPayload,
            $this->dhlBookingResponse,
            $this->dhlBookingError,
            $this->dhlBookedAt,
            $this->dhlCancelledAt,
            $this->dhlCancelledBy,
            $this->dhlCancellationReason,
            $this->receiverAddress,
            $this->freightProfileId,
        );
    }

    public function contactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function contactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function destinationCountry(): ?string
    {
        return $this->destinationCountry;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function totalAmount(): ?float
    {
        return $this->totalAmount;
    }

    public function processedAt(): ?DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function isBooked(): bool
    {
        return $this->isBooked;
    }

    public function bookedAt(): ?DateTimeImmutable
    {
        return $this->bookedAt;
    }

    public function bookedBy(): ?string
    {
        return $this->bookedBy;
    }

    public function shippedAt(): ?DateTimeImmutable
    {
        return $this->shippedAt;
    }

    public function lastExportFilename(): ?string
    {
        return $this->lastExportFilename;
    }

    /**
     * @return array<int,ShipmentOrderItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * @return array<int,ShipmentPackage>
     */
    public function packages(): array
    {
        return $this->packages;
    }

    /**
     * @return array<int,string>
     */
    public function trackingNumbers(): array
    {
        return $this->trackingNumbers;
    }

    /**
     * @return array<string,mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function dhlShipmentId(): ?string
    {
        return $this->dhlShipmentId;
    }

    public function dhlLabelUrl(): ?string
    {
        return $this->dhlLabelUrl;
    }

    public function dhlLabelPdfBase64(): ?string
    {
        return $this->dhlLabelPdfBase64;
    }

    public function dhlPickupReference(): ?string
    {
        return $this->dhlPickupReference;
    }

    public function dhlProductId(): ?string
    {
        return $this->dhlProductId;
    }

    /**
     * @return array<string,mixed>
     */
    public function dhlBookingPayload(): array
    {
        return $this->dhlBookingPayload;
    }

    /**
     * @return array<string,mixed>
     */
    public function dhlBookingResponse(): array
    {
        return $this->dhlBookingResponse;
    }

    public function dhlBookingError(): ?string
    {
        return $this->dhlBookingError;
    }

    public function dhlBookedAt(): ?DateTimeImmutable
    {
        return $this->dhlBookedAt;
    }

    public function dhlCancelledAt(): ?string
    {
        return $this->dhlCancelledAt;
    }

    public function dhlCancelledBy(): ?string
    {
        return $this->dhlCancelledBy;
    }

    public function dhlCancellationReason(): ?string
    {
        return $this->dhlCancellationReason;
    }

    /**
     * Returns the typed receiver address if the order has been migrated to first-class
     * receiver columns. During the metadata->columns migration window, callers SHOULD
     * fall back to {@see metadata()} only when this returns null.
     */
    public function receiverAddress(): ?ShipmentReceiverAddress
    {
        return $this->receiverAddress;
    }

    public function withReceiverAddress(
        ShipmentReceiverAddress $receiverAddress,
        ?DateTimeImmutable $updatedAt = null
    ): self {
        return new self(
            $this->id,
            $this->externalOrderId,
            $this->customerNumber,
            $this->plentyOrderId,
            $this->orderType,
            $this->senderProfileId,
            $this->senderCode,
            $this->contactEmail,
            $this->contactPhone,
            $this->destinationCountry,
            $this->currency,
            $this->totalAmount,
            $this->processedAt,
            $this->isBooked,
            $this->bookedAt,
            $this->bookedBy,
            $this->shippedAt,
            $this->lastExportFilename,
            $this->items,
            $this->packages,
            $this->trackingNumbers,
            $this->metadata,
            $this->createdAt,
            $updatedAt ?? new DateTimeImmutable,
            $this->dhlShipmentId,
            $this->dhlLabelUrl,
            $this->dhlLabelPdfBase64,
            $this->dhlPickupReference,
            $this->dhlProductId,
            $this->dhlBookingPayload,
            $this->dhlBookingResponse,
            $this->dhlBookingError,
            $this->dhlBookedAt,
            $this->dhlCancelledAt,
            $this->dhlCancelledBy,
            $this->dhlCancellationReason,
            $receiverAddress,
            $this->freightProfileId,
        );
    }

    /**
     * Returns the linked FulfillmentFreightProfile shipping_profile_id, if any.
     *
     * Resolved by {@see DhlSettingsResolver} to apply the per-profile
     * AccountNumber override (Profile.account_number > System Default).
     * `null` means: fall back to the system default.
     */
    public function freightProfileId(): ?int
    {
        return $this->freightProfileId;
    }

    public function withFreightProfileId(
        ?int $freightProfileId,
        ?DateTimeImmutable $updatedAt = null
    ): self {
        if ($freightProfileId !== null && $freightProfileId <= 0) {
            throw new InvalidArgumentException('freightProfileId must be a positive integer or null.');
        }

        return new self(
            $this->id,
            $this->externalOrderId,
            $this->customerNumber,
            $this->plentyOrderId,
            $this->orderType,
            $this->senderProfileId,
            $this->senderCode,
            $this->contactEmail,
            $this->contactPhone,
            $this->destinationCountry,
            $this->currency,
            $this->totalAmount,
            $this->processedAt,
            $this->isBooked,
            $this->bookedAt,
            $this->bookedBy,
            $this->shippedAt,
            $this->lastExportFilename,
            $this->items,
            $this->packages,
            $this->trackingNumbers,
            $this->metadata,
            $this->createdAt,
            $updatedAt ?? new DateTimeImmutable,
            $this->dhlShipmentId,
            $this->dhlLabelUrl,
            $this->dhlLabelPdfBase64,
            $this->dhlPickupReference,
            $this->dhlProductId,
            $this->dhlBookingPayload,
            $this->dhlBookingResponse,
            $this->dhlBookingError,
            $this->dhlBookedAt,
            $this->dhlCancelledAt,
            $this->dhlCancelledBy,
            $this->dhlCancellationReason,
            $this->receiverAddress,
            $freightProfileId,
        );
    }
}
