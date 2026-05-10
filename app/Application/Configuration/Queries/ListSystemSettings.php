<?php

namespace App\Application\Configuration\Queries;

use App\Application\Configuration\SystemSettingService;
use App\Domain\Configuration\SystemSetting;

final class ListSystemSettings
{
    public function __construct(private readonly SystemSettingService $service) {}

    /**
     * @return array<int,SystemSetting>
     */
    public function __invoke(): array
    {
        return $this->normalizeIterable($this->service->all());
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
