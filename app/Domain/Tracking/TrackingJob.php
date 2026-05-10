<?php

declare(strict_types=1);

namespace App\Domain\Tracking;

use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;

final class TrackingJob
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    private const VALID_STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_RESERVED,
        self::STATUS_RUNNING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        'pending', // source value, normalized to scheduled
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $result
     */
    private function __construct(
        private readonly Identifier $id,
        private readonly string $jobType,
        private readonly string $status,
        private readonly ?DateTimeImmutable $scheduledAt,
        private readonly ?DateTimeImmutable $startedAt,
        private readonly ?DateTimeImmutable $finishedAt,
        private readonly int $attempt,
        private readonly ?string $lastError,
        private readonly array $payload,
        private readonly array $result,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @psalm-param array<string,mixed> $payload
     * @psalm-param array<string,mixed> $result
     */
    public static function hydrate(
        Identifier $id,
        string $jobType,
        string $status,
        ?DateTimeImmutable $scheduledAt,
        ?DateTimeImmutable $startedAt,
        ?DateTimeImmutable $finishedAt,
        int $attempt,
        ?string $lastError,
        array $payload,
        array $result,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        $normalizedJobType = self::sanitizeJobType($jobType);
        $normalizedStatus = self::sanitizeStatus($status);
        $normalizedAttempt = max(0, $attempt);
        $normalizedError = $lastError ? self::sanitizeNullableString($lastError) : null;

        self::guardChronology($scheduledAt, $startedAt, $finishedAt, $createdAt, $updatedAt);
        self::guardResultPayload($payload);
        self::guardResultPayload($result);

        return new self(
            $id,
            $normalizedJobType,
            $normalizedStatus,
            $scheduledAt,
            $startedAt,
            $finishedAt,
            $normalizedAttempt,
            $normalizedError,
            $payload,
            $result,
            $createdAt,
            $updatedAt,
        );
    }

    public function id(): Identifier
    {
        return $this->id;
    }

    public function jobType(): string
    {
        return $this->jobType;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function scheduledAt(): ?DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function startedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function finishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function attempt(): int
    {
        return $this->attempt;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @return array<string,mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * @return array<string,mixed>
     */
    public function result(): array
    {
        return $this->result;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function reserve(DateTimeImmutable $now): self
    {
        if (! in_array($this->status, [self::STATUS_SCHEDULED, self::STATUS_RESERVED], true)) {
            throw new \RuntimeException('Only scheduled jobs can be reserved.');
        }

        return self::hydrate(
            $this->id,
            $this->jobType,
            self::STATUS_RESERVED,
            $this->scheduledAt,
            $this->startedAt,
            $this->finishedAt,
            $this->attempt,
            $this->lastError,
            $this->payload,
            $this->result,
            $this->createdAt,
            $now,
        );
    }

    public function start(DateTimeImmutable $now): self
    {
        if (! in_array($this->status, [self::STATUS_SCHEDULED, self::STATUS_RESERVED], true)) {
            throw new \RuntimeException('Job must be scheduled or reserved before it can start.');
        }

        return self::hydrate(
            $this->id,
            $this->jobType,
            self::STATUS_RUNNING,
            $this->scheduledAt,
            $now,
            null,
            $this->attempt + 1,
            null,
            $this->payload,
            $this->result,
            $this->createdAt,
            $now,
        );
    }

    /**
     * @param  array<string,mixed>  $result
     */
    public function complete(DateTimeImmutable $now, array $result = []): self
    {
        if ($this->status !== self::STATUS_RUNNING) {
            throw new \RuntimeException('Only running jobs can be completed.');
        }

        self::guardResultPayload($result);

        return self::hydrate(
            $this->id,
            $this->jobType,
            self::STATUS_COMPLETED,
            $this->scheduledAt,
            $this->startedAt,
            $now,
            $this->attempt,
            null,
            $this->payload,
            $result,
            $this->createdAt,
            $now,
        );
    }

    /**
     * @param  array<string,mixed>  $result
     */
    public function fail(DateTimeImmutable $now, array $result = [], ?string $error = null): self
    {
        if ($this->status !== self::STATUS_RUNNING) {
            throw new \RuntimeException('Only running jobs can fail.');
        }

        self::guardResultPayload($result);

        $normalizedError = $error !== null ? self::sanitizeNonEmptyString($error, 'error') : 'Job failed.';

        return self::hydrate(
            $this->id,
            $this->jobType,
            self::STATUS_FAILED,
            $this->scheduledAt,
            $this->startedAt,
            $now,
            $this->attempt,
            $normalizedError,
            $this->payload,
            $result,
            $this->createdAt,
            $now,
        );
    }

    public function retry(DateTimeImmutable $now, ?DateTimeImmutable $scheduledAt = null): self
    {
        if (! in_array($this->status, [self::STATUS_FAILED, self::STATUS_COMPLETED], true)) {
            throw new \RuntimeException('Only completed or failed jobs can be retried.');
        }

        $target = $scheduledAt ?? $now;

        return self::hydrate(
            $this->id,
            $this->jobType,
            self::STATUS_SCHEDULED,
            $target,
            null,
            null,
            $this->attempt,
            null,
            $this->payload,
            [],
            $this->createdAt,
            $now,
        );
    }

    /**
     * @psalm-param array<string,mixed> $payload
     */
    public static function schedule(
        Identifier $id,
        string $jobType,
        array $payload,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $scheduledAt = null
    ): self {
        $scheduledFor = $scheduledAt ?? $createdAt;

        return self::hydrate(
            $id,
            $jobType,
            self::STATUS_SCHEDULED,
            $scheduledFor,
            null,
            null,
            0,
            null,
            $payload,
            [],
            $createdAt,
            $createdAt,
        );
    }

    private static function sanitizeJobType(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new \InvalidArgumentException('Job type must be a non-empty string.');
        }

        return $normalized;
    }

    private static function sanitizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        if ($normalized === 'pending') {
            return self::STATUS_SCHEDULED;
        }

        if (! in_array($normalized, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid tracking job status "%s".', $status));
        }

        return $normalized;
    }

    private static function sanitizeNonEmptyString(string $value, string $fieldName): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new \InvalidArgumentException(sprintf('%s must be a non-empty string.', $fieldName));
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
        ?DateTimeImmutable $scheduledAt,
        ?DateTimeImmutable $startedAt,
        ?DateTimeImmutable $finishedAt,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): void {
        if ($updatedAt < $createdAt) {
            throw new \InvalidArgumentException('updated_at must be greater than or equal to created_at.');
        }

        if ($startedAt && $scheduledAt && $startedAt < $scheduledAt) {
            throw new \InvalidArgumentException('started_at must be greater than or equal to scheduled_at.');
        }

        if ($finishedAt && $startedAt && $finishedAt < $startedAt) {
            throw new \InvalidArgumentException('finished_at must be greater than or equal to started_at.');
        }
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    /**
     * @param  array<int|string, mixed>  $payload
     *
     * @noinspection PhpUnusedParameterInspection
     *
     * Verteidigungs-Hook: PHP-Array-Keys sind bereits int|string, also gibt es nichts
     * zu prüfen. Der Hook bleibt für künftige fachliche Whitelist-Checks erhalten.
     */
    private static function guardResultPayload(array $payload): void
    {
        // Reserviert für künftige fachliche Asserts.
    }
}
