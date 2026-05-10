<?php

declare(strict_types=1);

namespace App\Domain\Tracking;

use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;

final class TrackingAlert
{
    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_ERROR = 'error';

    public const SEVERITY_CRITICAL = 'critical';

    private const VALID_SEVERITIES = [
        self::SEVERITY_INFO,
        self::SEVERITY_WARNING,
        self::SEVERITY_ERROR,
        self::SEVERITY_CRITICAL,
    ];

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function __construct(
        private readonly Identifier $id,
        private readonly ?Identifier $shipmentId,
        private readonly string $alertType,
        private readonly string $severity,
        private readonly ?string $channel,
        private readonly string $message,
        private readonly ?DateTimeImmutable $sentAt,
        private readonly ?DateTimeImmutable $acknowledgedAt,
        private readonly array $metadata,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @psalm-param array<string,mixed> $metadata
     */
    public static function hydrate(
        Identifier $id,
        ?Identifier $shipmentId,
        string $alertType,
        string $severity,
        ?string $channel,
        string $message,
        ?DateTimeImmutable $sentAt,
        ?DateTimeImmutable $acknowledgedAt,
        array $metadata,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        $normalizedAlertType = self::sanitizeAlertType($alertType);
        $normalizedSeverity = self::sanitizeSeverity($severity);
        $normalizedMessage = self::sanitizeMessage($message);
        $normalizedChannel = self::sanitizeNullableString($channel);

        self::guardChronology($createdAt, $sentAt, $acknowledgedAt, $updatedAt);
        self::guardMetadata($metadata);

        return new self(
            $id,
            $shipmentId,
            $normalizedAlertType,
            $normalizedSeverity,
            $normalizedChannel,
            $normalizedMessage,
            $sentAt,
            $acknowledgedAt,
            $metadata,
            $createdAt,
            $updatedAt,
        );
    }

    public function id(): Identifier
    {
        return $this->id;
    }

    public function shipmentId(): ?Identifier
    {
        return $this->shipmentId;
    }

    public function alertType(): string
    {
        return $this->alertType;
    }

    public function severity(): string
    {
        return $this->severity;
    }

    public function channel(): ?string
    {
        return $this->channel;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function sentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function acknowledgedAt(): ?DateTimeImmutable
    {
        return $this->acknowledgedAt;
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

    public function isAcknowledged(): bool
    {
        return $this->acknowledgedAt !== null;
    }

    public function isSent(): bool
    {
        return $this->sentAt !== null;
    }

    /**
     * @psalm-param array<string,mixed> $metadata
     */
    public static function raise(
        Identifier $id,
        string $alertType,
        string $severity,
        string $message,
        ?Identifier $shipmentId,
        ?string $channel,
        array $metadata,
        DateTimeImmutable $createdAt
    ): self {
        return self::hydrate(
            $id,
            $shipmentId,
            $alertType,
            $severity,
            $channel,
            $message,
            null,
            null,
            $metadata,
            $createdAt,
            $createdAt,
        );
    }

    public function markSent(DateTimeImmutable $sentAt): self
    {
        if ($this->isSent()) {
            throw new \RuntimeException('Tracking alert was already marked as sent.');
        }

        return self::hydrate(
            $this->id,
            $this->shipmentId,
            $this->alertType,
            $this->severity,
            $this->channel,
            $this->message,
            $sentAt,
            $this->acknowledgedAt,
            $this->metadata,
            $this->createdAt,
            $sentAt,
        );
    }

    public function acknowledge(DateTimeImmutable $acknowledgedAt): self
    {
        if ($this->isAcknowledged()) {
            throw new \RuntimeException('Tracking alert was already acknowledged.');
        }

        return self::hydrate(
            $this->id,
            $this->shipmentId,
            $this->alertType,
            $this->severity,
            $this->channel,
            $this->message,
            $this->sentAt,
            $acknowledgedAt,
            $this->metadata,
            $this->createdAt,
            $acknowledgedAt,
        );
    }

    private static function sanitizeAlertType(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new \InvalidArgumentException('Alert type must be a non-empty string.');
        }

        return $normalized;
    }

    private static function sanitizeSeverity(string $value): string
    {
        $normalized = strtolower(trim($value));

        if (! in_array($normalized, self::VALID_SEVERITIES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid severity "%s" for tracking alert.', $value));
        }

        return $normalized;
    }

    private static function sanitizeMessage(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new \InvalidArgumentException('Alert message must not be empty.');
        }

        return $normalized;
    }

    private static function sanitizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private static function guardChronology(
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $sentAt,
        ?DateTimeImmutable $acknowledgedAt,
        DateTimeImmutable $updatedAt
    ): void {
        if ($updatedAt < $createdAt) {
            throw new \InvalidArgumentException('updated_at must be greater than or equal to created_at.');
        }

        if ($sentAt && $sentAt < $createdAt) {
            throw new \InvalidArgumentException('sent_at must be greater than or equal to created_at.');
        }

        if ($acknowledgedAt && ($sentAt && $acknowledgedAt < $sentAt)) {
            throw new \InvalidArgumentException('acknowledged_at must be greater than or equal to sent_at.');
        }
    }

    /**
     * @psalm-param array<string,mixed> $metadata
     */
    /**
     * @param  array<int|string, mixed>  $metadata
     *
     * @noinspection PhpUnusedParameterInspection
     *
     * Verteidigungs-Hook: Array-Keys sind in PHP per Definition int|string,
     * also gibt es nichts zu prüfen. Der Hook bleibt für künftige zusätzliche
     * Invarianten (z.B. erlaubte Key-Whitelist) erhalten.
     */
    private static function guardMetadata(array $metadata): void
    {
        // Reserviert für künftige fachliche Asserts (Whitelist von Metadata-Keys o.ä.).
    }
}
