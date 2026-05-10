<?php

declare(strict_types=1);

namespace App\Domain\Dispatch;

use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use InvalidArgumentException;

final class DispatchScan
{
    /**
     * @param  array<string,mixed>  $metadata
     */
    private function __construct(
        private readonly Identifier $id,
        private readonly Identifier $dispatchListId,
        private readonly string $barcode,
        private readonly ?Identifier $shipmentOrderId,
        private readonly ?Identifier $capturedByUserId,
        private readonly ?DateTimeImmutable $capturedAt,
        private readonly array $metadata,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @psalm-param array<string,mixed> $metadata
     */
    public static function hydrate(
        Identifier $id,
        Identifier $dispatchListId,
        string $barcode,
        ?Identifier $shipmentOrderId,
        ?Identifier $capturedByUserId,
        ?DateTimeImmutable $capturedAt,
        array $metadata,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        $normalized_barcode = self::sanitize_barcode($barcode);
        $normalized_metadata = self::normalize_metadata($metadata);

        return new self(
            $id,
            $dispatchListId,
            $normalized_barcode,
            $shipmentOrderId,
            $capturedByUserId,
            $capturedAt,
            $normalized_metadata,
            $createdAt,
            $updatedAt,
        );
    }

    public function id(): Identifier
    {
        return $this->id;
    }

    public function dispatchListId(): Identifier
    {
        return $this->dispatchListId;
    }

    public function barcode(): string
    {
        return $this->barcode;
    }

    public function shipmentOrderId(): ?Identifier
    {
        return $this->shipmentOrderId;
    }

    public function capturedByUserId(): ?Identifier
    {
        return $this->capturedByUserId;
    }

    public function capturedAt(): ?DateTimeImmutable
    {
        return $this->capturedAt;
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

    private static function sanitize_barcode(string $barcode): string
    {
        $barcode = trim($barcode);

        if ($barcode === '') {
            throw new InvalidArgumentException('Barcode must be a non-empty string.');
        }

        return $barcode;
    }

    /**
     * @psalm-param array<string,mixed> $metadata
     *
     * @psalm-return array<string,mixed>
     */
    private static function normalize_metadata(array $metadata): array
    {
        $normalized = [];

        foreach ($metadata as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Metadata keys must be non-empty strings.');
            }

            $trimmed_key = trim($key);

            if ($trimmed_key === '') {
                throw new InvalidArgumentException('Metadata keys must be non-empty strings.');
            }

            $normalized[$trimmed_key] = is_string($value) ? trim($value) : $value;
        }

        return $normalized;
    }
}
