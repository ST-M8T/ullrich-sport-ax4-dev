<?php

namespace App\Application\Configuration;

use App\Domain\Configuration\Contracts\MailTemplateRepository;
use App\Domain\Configuration\MailTemplate;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;

final class MailTemplateService
{
    public function __construct(private readonly MailTemplateRepository $templates) {}

    public function upsert(
        ?Identifier $id,
        string $templateKey,
        string $subject,
        ?string $bodyHtml,
        ?string $bodyText,
        bool $isActive,
        ?string $description = null,
        ?int $updatedBy = null,
    ): MailTemplate {
        $identifier = $id ?? $this->templates->nextIdentity();
        $now = new DateTimeImmutable;

        $template = MailTemplate::hydrate(
            $identifier,
            $templateKey,
            $description,
            $subject,
            $bodyHtml,
            $bodyText,
            $isActive,
            $updatedBy,
            $now,
            $now,
        );

        $this->templates->save($template);

        return $template;
    }

    public function get(string $templateKey): ?MailTemplate
    {
        return $this->templates->getByKey($templateKey);
    }

    public function delete(Identifier $id): void
    {
        $this->templates->delete($id);
    }

    /**
     * @return iterable<MailTemplate>
     */
    public function all(): iterable
    {
        return $this->templates->all();
    }
}
