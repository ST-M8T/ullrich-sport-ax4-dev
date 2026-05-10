<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;

final class MailTemplate
{
    private function __construct(
        private readonly Identifier $id,
        private readonly string $templateKey,
        private readonly ?string $description,
        private readonly string $subject,
        private readonly ?string $bodyHtml,
        private readonly ?string $bodyText,
        private readonly bool $isActive,
        private readonly ?int $updatedByUserId,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    ) {}

    public static function hydrate(
        Identifier $id,
        string $templateKey,
        ?string $description,
        string $subject,
        ?string $bodyHtml,
        ?string $bodyText,
        bool $isActive,
        ?int $updatedByUserId,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            $id,
            trim($templateKey),
            $description ? trim($description) : null,
            trim($subject),
            $bodyHtml,
            $bodyText,
            $isActive,
            $updatedByUserId,
            $createdAt,
            $updatedAt,
        );
    }

    public function id(): Identifier
    {
        return $this->id;
    }

    public function templateKey(): string
    {
        return $this->templateKey;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function bodyHtml(): ?string
    {
        return $this->bodyHtml;
    }

    public function bodyText(): ?string
    {
        return $this->bodyText;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function updatedByUserId(): ?int
    {
        return $this->updatedByUserId;
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
