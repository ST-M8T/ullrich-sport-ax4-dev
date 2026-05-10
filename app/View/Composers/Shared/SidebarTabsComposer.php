<?php

declare(strict_types=1);

namespace App\View\Composers\Shared;

use App\Support\UI\SidebarTabsService;
use Illuminate\View\View;

/**
 * Sidebar Tabs View Composer
 * Bereitet Tab-Navigation-Daten vor
 */
final class SidebarTabsComposer
{
    public function __construct(
        private readonly SidebarTabsService $sidebarTabsService
    ) {}

    public function compose(View $view): void
    {
        $data = $view->getData();

        $tabData = $this->sidebarTabsService->prepareTabData(
            $data['tabs'] ?? [],
            $data['activeTab'] ?? null,
            $data['baseUrl'] ?? null,
            $data['tabParam'] ?? 'tab'
        );

        $view->with($tabData);
    }
}
