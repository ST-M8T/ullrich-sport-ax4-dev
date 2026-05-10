<?php

declare(strict_types=1);

namespace App\Domain\Configuration\Contracts;

use App\Domain\Configuration\MailTemplate;
use App\Domain\Shared\ValueObjects\Identifier;

interface MailTemplateRepository
{
    public function nextIdentity(): Identifier;

    public function getByKey(string $templateKey): ?MailTemplate;

    public function save(MailTemplate $template): void;

    public function delete(Identifier $id): void;

    /**
     * @return iterable<MailTemplate>
     */
    public function all(): iterable;
}
