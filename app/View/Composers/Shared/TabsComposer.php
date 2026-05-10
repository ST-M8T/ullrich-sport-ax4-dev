<?php

declare(strict_types=1);

namespace App\View\Composers\Shared;

use App\Support\UI\TabsService;
use Illuminate\View\View;

/**
 * Tabs View Composer
 * Bereitet Tab-Navigation-Daten vor
 */
final class TabsComposer
{
    public function __construct(
        private readonly TabsService $tabsService
    ) {}

    public function compose(View $view): void
    {
        $data = $view->getData();

        $tabData = $this->tabsService->prepareTabData(
            $data['tabs'] ?? [],
            $data['activeTab'] ?? null,
            $data['baseUrl'] ?? null,
            $data['tabParam'] ?? 'tab'
        );

        $view->with($tabData);
    }
}
