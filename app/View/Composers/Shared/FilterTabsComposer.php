<?php

declare(strict_types=1);

namespace App\View\Composers\Shared;

use App\Support\UI\FilterTabsService;
use Illuminate\View\View;

/**
 * Filter Tabs View Composer
 * Bereitet Filter-Tab-Daten vor
 */
final class FilterTabsComposer
{
    public function __construct(
        private readonly FilterTabsService $filterTabsService
    ) {}

    public function compose(View $view): void
    {
        $data = $view->getData();

        $tabData = $this->filterTabsService->prepareFilterTabData(
            $data['tabs'] ?? [],
            $data['activeTab'] ?? null,
            $data['baseUrl'] ?? null,
            $data['queryParams'] ?? []
        );

        $view->with($tabData);
    }
}
