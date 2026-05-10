<?php

declare(strict_types=1);

namespace App\View\Composers\Shared;

use App\Support\UI\LayoutService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Admin Layout View Composer
 * Bereitet Admin-Layout-Daten vor
 */
final class AdminLayoutComposer
{
    public function __construct(
        private readonly LayoutService $layoutService
    ) {}

    public function compose(View $view): void
    {
        $data = $view->getData();

        $pageTitle = $data['pageTitle'] ?? null;
        $showHeader = (bool) ($data['showHeader'] ?? false);
        $showSidebar = (bool) ($data['showSidebar'] ?? true);
        $showFooter = (bool) ($data['showFooter'] ?? true);
        $logoutUrl = $data['logoutUrl'] ?? null;
        $identityUser = $data['identityUser'] ?? null;
        $currentUser = $data['currentUser'] ?? null;
        $appName = $data['appName'] ?? null;
        $companyLogo = $data['companyLogo'] ?? null;
        $dhlLogo = $data['dhlLogo'] ?? null;

        $authUser = Auth::user();

        $tagline = $data['tagline'] ?? null;
        $breadcrumbs = $data['breadcrumbs'] ?? null;

        $documentTitle = $this->layoutService->prepareDocumentTitle($pageTitle);
        $layoutClass = $this->layoutService->prepareLayoutClass($showSidebar);
        $logoutHref = $this->layoutService->prepareLogoutUrl($logoutUrl);
        $displayName = $this->layoutService->prepareDisplayName($identityUser, $authUser, $currentUser);
        $uiConfig = $this->layoutService->prepareUiConfig($appName, $companyLogo, $dhlLogo, $tagline);
        $breadcrumbItems = $this->layoutService->prepareBreadcrumbItems($breadcrumbs, $pageTitle);

        $view->with([
            'documentTitle' => $documentTitle,
            'layoutClass' => $layoutClass,
            'logoutHref' => $logoutHref,
            'displayName' => $displayName,
            'uiConfig' => $uiConfig,
            'authUser' => $authUser,
            'breadcrumbItems' => $breadcrumbItems,
        ]);
    }
}
