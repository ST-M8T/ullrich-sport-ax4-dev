<?php

declare(strict_types=1);

namespace App\Domain\Monitoring;

use DateTimeImmutable;

final class SystemJobEntry
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $result
     */
    private function __construct(
        private readonly int $id,
        private readonly string $jobName,
        private readonly ?string $jobType,
        private readonly ?string $runContext,
        private readonly string $status,
        private readonly ?DateTimeImmutable $scheduledAt,
        private readonly ?DateTimeImmutable $startedAt,
        private readonly ?DateTimeImmutable $finishedAt,
        private readonly ?int $durationMs,
        private readonly array $payload,
        private readonly array $result,
        private readonly ?string $errorMessage,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @psalm-param array<string,mixed> $payload
     * @psalm-param array<string,mixed> $result
     */
    public static function hydrate(
        int $id,
        string $jobName,
        ?string $jobType,
        ?string $runContext,
        string $status,
        ?DateTimeImmutable $scheduledAt,
        ?DateTimeImmutable $startedAt,
        ?DateTimeImmutable $finishedAt,
        ?int $durationMs,
        array $payload,
        array $result,
        ?string $errorMessage,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            $id,
            trim($jobName),
            $jobType ? trim($jobType) : null,
            $runContext ? trim($runContext) : null,
            trim($status),
            $scheduledAt,
            $startedAt,
            $finishedAt,
            $durationMs !== null ? max(0, $durationMs) : null,
            $payload,
            $result,
            $errorMessage ? trim($errorMessage) : null,
            $createdAt,
            $updatedAt,
        );
    }

    public function id(): int
    {
        return $this->id;
    }

    public function jobName(): string
    {
        return $this->jobName;
    }

    public function jobType(): ?string
    {
        return $this->jobType;
    }

    public function runContext(): ?string
    {
        return $this->runContext;
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

    public function durationMs(): ?int
    {
        return $this->durationMs;
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

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
