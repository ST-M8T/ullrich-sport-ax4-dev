<?php

namespace App\Application\Configuration\Queries;

use App\Domain\Configuration\Contracts\MailTemplateRepository;
use App\Domain\Configuration\MailTemplate;

final class ListMailTemplates
{
    public function __construct(private readonly MailTemplateRepository $templates) {}

    /**
     * @return array<int,MailTemplate>
     */
    public function __invoke(): array
    {
        return $this->normalizeIterable($this->templates->all());
    }

    /**
     * @template T
     *
     * @param  iterable<T>  $items
     * @return array<int,T>
     */
    private function normalizeIterable(iterable $items): array
    {
        return is_array($items) ? array_values($items) : iterator_to_array($items, false);
    }
}
