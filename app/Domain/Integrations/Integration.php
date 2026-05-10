<?php

declare(strict_types=1);

namespace App\Domain\Integrations;

use App\Domain\Shared\ValueObjects\Identifier;

/**
 * Integration Domain Entity
 * Repräsentiert eine externe Integration im System
 * DDD: Domain Model - Rich Domain Model mit Business Logic
 */
final class Integration
{
    /**
     * @param  array<string, mixed>  $configuration
     */
    private function __construct(
        private readonly Identifier $id,
        private readonly string $key,
        private readonly string $name,
        private readonly string $description,
        private readonly IntegrationType $type,
        private readonly IntegrationStatus $status,
        private readonly array $configuration,
        private readonly ?\DateTimeImmutable $createdAt = null,
        private readonly ?\DateTimeImmutable $updatedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $configuration
     */
    public static function create(
        Identifier $id,
        string $key,
        string $name,
        string $description,
        IntegrationType $type,
        array $configuration = [],
    ): self {
        return new self(
            id: $id,
            key: $key,
            name: $name,
            description: $description,
            type: $type,
            status: IntegrationStatus::INACTIVE,
            configuration: $configuration,
            createdAt: new \DateTimeImmutable,
            updatedAt: new \DateTimeImmutable,
        );
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    public static function fromPersistence(
        Identifier $id,
        string $key,
        string $name,
        string $description,
        IntegrationType $type,
        IntegrationStatus $status,
        array $configuration,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
    ): self {
        return new self(
            id: $id,
            key: $key,
            name: $name,
            description: $description,
            type: $type,
            status: $status,
            configuration: $configuration,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function id(): Identifier
    {
        return $this->id;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function type(): IntegrationType
    {
        return $this->type;
    }

    public function status(): IntegrationStatus
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function configuration(): array
    {
        return $this->configuration;
    }

    public function isActive(): bool
    {
        return $this->status === IntegrationStatus::ACTIVE;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->configuration);
    }

    public function createdAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function withConfiguration(array $configuration): self
    {
        return new self(
            id: $this->id,
            key: $this->key,
            name: $this->name,
            description: $this->description,
            type: $this->type,
            status: $this->status,
            configuration: $configuration,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable,
        );
    }

    public function withStatus(IntegrationStatus $status): self
    {
        return new self(
            id: $this->id,
            key: $this->key,
            name: $this->name,
            description: $this->description,
            type: $this->type,
            status: $status,
            configuration: $this->configuration,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable,
        );
    }
}
