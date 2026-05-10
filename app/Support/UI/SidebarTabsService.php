<?php

declare(strict_types=1);

namespace App\Support\UI;

use Illuminate\Http\Request;

/**
 * Sidebar Tabs Service
 * Bereitet Tab-Navigation-Daten vor
 * SOLID: Single Responsibility - Nur Tab-URL-Logik
 * DDD: Application Service - Orchestriert Tab-Logik
 */
final class SidebarTabsService
{
    public function __construct(
        private readonly Request $request
    ) {}

    /**
     * Bereitet Tab-Daten für Komponente vor
     *
     * @param  array<string, mixed>  $tabs
     * @return array<string, mixed>
     */
    public function prepareTabData(
        array $tabs,
        ?string $activeTab = null,
        ?string $baseUrl = null,
        string $tabParam = 'tab'
    ): array {
        if ($baseUrl === null) {
            $baseUrl = $this->request->url();
        }

        $baseUrlParts = parse_url($baseUrl);
        $baseQueryParams = [];
        if (isset($baseUrlParts['query'])) {
            parse_str($baseUrlParts['query'], $baseQueryParams);
        }

        $cleanBaseUrl = ($baseUrlParts['scheme'] ?? '').'://'.($baseUrlParts['host'] ?? '');
        if (isset($baseUrlParts['port'])) {
            $cleanBaseUrl .= ':'.$baseUrlParts['port'];
        }
        $cleanBaseUrl .= ($baseUrlParts['path'] ?? '');

        $queryParams = array_merge($this->request->query(), $baseQueryParams);
        unset($queryParams[$tabParam]);

        $activeTab = $activeTab ?? ($this->request->query($tabParam) ?? array_key_first($tabs));

        $processedTabs = [];
        foreach ($tabs as $slug => $tab) {
            $isActive = $activeTab === $slug;
            $tabLabel = is_array($tab) ? ($tab['label'] ?? $slug) : $tab;
            $tabBadge = is_array($tab) ? ($tab['badge'] ?? $tab['count'] ?? null) : null;
            $tabUrl = is_array($tab) && isset($tab['url'])
                ? $tab['url']
                : ($cleanBaseUrl.'?'.http_build_query(array_merge($queryParams, [$tabParam => $slug])));

            $processedTabs[] = [
                'slug' => $slug,
                'isActive' => $isActive,
                'label' => $tabLabel,
                'badge' => $tabBadge,
                'url' => $tabUrl,
            ];
        }

        return [
            'cleanBaseUrl' => $cleanBaseUrl,
            'queryParams' => $queryParams,
            'activeTab' => $activeTab,
            'processedTabs' => $processedTabs,
        ];
    }
}
