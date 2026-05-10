<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Exports;

use DateTimeImmutable;

final class ShipmentExportFilters
{
    public function __construct(
        public readonly ?DateTimeImmutable $processedFrom = null,
        public readonly ?DateTimeImmutable $processedTo = null,
        public readonly ?string $senderCode = null,
        public readonly ?string $destinationCountry = null,
        public readonly ?bool $isBooked = null,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            self::parseDate($input['processed_from'] ?? null),
            self::parseDate($input['processed_to'] ?? null, endOfDay: true),
            self::parseString($input['sender_code'] ?? null),
            self::parseCountry($input['destination_country'] ?? null),
            self::parseBool($input['is_booked'] ?? null),
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'processed_from' => $this->processedFrom?->format('Y-m-d'),
            'processed_to' => $this->processedTo?->format('Y-m-d'),
            'sender_code' => $this->senderCode,
            'destination_country' => $this->destinationCountry,
            'is_booked' => $this->isBooked,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function toRepositoryFilters(): array
    {
        $filters = [];

        if ($this->processedFrom) {
            $filters['processed_from'] = $this->processedFrom;
        }

        if ($this->processedTo) {
            $filters['processed_to'] = $this->processedTo;
        }

        if ($this->senderCode !== null && $this->senderCode !== '') {
            $filters['sender_code'] = $this->senderCode;
        }

        if ($this->destinationCountry !== null && $this->destinationCountry !== '') {
            $filters['destination_country'] = $this->destinationCountry;
        }

        if ($this->isBooked !== null) {
            $filters['is_booked'] = $this->isBooked;
        }

        return $filters;
    }

    public function isEmpty(): bool
    {
        return ! $this->processedFrom
            && ! $this->processedTo
            && ! $this->senderCode
            && ! $this->destinationCountry
            && $this->isBooked === null;
    }

    private static function parseDate(mixed $value, bool $endOfDay = false): ?DateTimeImmutable
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', trim($value));
            if ($date !== false) {
                if ($endOfDay) {
                    return $date->setTime(23, 59, 59);
                }

                return $date->setTime(0, 0, 0);
            }
        }

        return null;
    }

    private static function parseString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function parseCountry(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtoupper(trim($value));

        if ($value === '') {
            return null;
        }

        return strlen($value) === 2 ? $value : null;
    }

    private static function parseBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) ((int) $value);
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === '1' || $normalized === 'true' || $normalized === 'on') {
                return true;
            }

            if ($normalized === '0' || $normalized === 'false' || $normalized === 'off') {
                return false;
            }
        }

        return null;
    }
}
