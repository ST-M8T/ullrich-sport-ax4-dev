<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Masterdata;

use App\Domain\Shared\ValueObjects\Identifier;

final class FulfillmentSenderRule
{
    private function __construct(
        private readonly Identifier $id,
        private readonly int $priority,
        private readonly string $ruleType,
        private readonly string $matchValue,
        private readonly Identifier $targetSenderId,
        private readonly bool $isActive,
        private readonly ?string $description,
    ) {}

    public static function hydrate(
        Identifier $id,
        int $priority,
        string $ruleType,
        string $matchValue,
        Identifier $targetSenderId,
        bool $isActive,
        ?string $description,
    ): self {
        return new self(
            $id,
            max(0, $priority),
            trim($ruleType),
            trim($matchValue),
            $targetSenderId,
            $isActive,
            $description ? trim($description) : null,
        );
    }

    public function id(): Identifier
    {
        return $this->id;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function ruleType(): string
    {
        return $this->ruleType;
    }

    public function matchValue(): string
    {
        return $this->matchValue;
    }

    public function targetSenderId(): Identifier
    {
        return $this->targetSenderId;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function description(): ?string
    {
        return $this->description;
    }
}
